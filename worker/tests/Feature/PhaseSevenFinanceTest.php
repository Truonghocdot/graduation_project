<?php

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\RoleKey;
use App\Enums\ServiceType;
use App\Models\DriverBankAccount;
use App\Models\LedgerAccount;
use App\Models\Role;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WithdrawalRequest;
use App\Services\Finance\RefundService;
use App\Services\Finance\WithdrawalService;
use Database\Seeders\RoleSeeder;
use Laravel\Sanctum\Sanctum;
use Tests\Support\ExecutionScenarioBuilder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('creates and completes a SePay top-up exactly once for duplicate webhooks', function () {
    config()->set([
        'services.sepay.webhook_secret' => 'sepay-secret',
        'finance.vietqr.bank_code' => 'MB',
        'finance.vietqr.account_number' => '0123456789',
        'finance.vietqr.account_name' => 'PROJECT',
    ]);
    $customer = User::factory()->create();
    Sanctum::actingAs($customer, ['customer:*']);

    $response = $this->postJson('/api/v1/wallet/topups', [
        'amount' => 200_000,
    ], ['Idempotency-Key' => 'topup-create-key'])->assertCreated();
    $reference = $response->json('data.vietqr_reference');
    $payload = [
        'event_id' => 'sepay-event-1',
        'transaction_id' => 'sepay-transaction-1',
        'reference' => $reference,
        'amount' => 200_000,
    ];

    $this->postJson('/api/v1/webhooks/sepay', $payload, [
        'X-SePay-Secret' => 'sepay-secret',
    ])->assertOk();
    $this->postJson('/api/v1/webhooks/sepay', $payload, [
        'X-SePay-Secret' => 'sepay-secret',
    ])->assertOk();

    $this->assertDatabaseCount('wallet_topups', 1);
    $this->assertDatabaseCount('webhook_receipts', 1);
    $this->assertDatabaseCount('ledger_transactions', 1);
    $this->assertDatabaseCount('ledger_entries', 2);
    $this->assertDatabaseHas('wallets', [
        'user_id' => $customer->id,
        'balance' => 200_000,
    ]);
});

test('rejects a SePay webhook with an invalid secret', function () {
    config()->set('services.sepay.webhook_secret', 'correct-secret');

    $this->postJson('/api/v1/webhooks/sepay', [
        'event_id' => 'invalid-event',
        'transaction_id' => 'invalid-transaction',
        'reference' => 'UNKNOWN',
        'amount' => 100_000,
    ], ['X-SePay-Secret' => 'wrong-secret'])->assertUnauthorized();

    $this->assertDatabaseCount('webhook_receipts', 0);
});

test('reserves and completes a driver withdrawal with balanced ledger entries', function () {
    $scenario = ExecutionScenarioBuilder::create(ServiceType::Drive);
    $scenario['driver_wallet']->forceFill(['balance' => 300_000])->save();
    $bankAccount = DriverBankAccount::query()->create([
        'driver_profile_id' => $scenario['profile']->id,
        'bank_code' => 'MB',
        'account_number_encrypted' => '0123456789',
        'account_number_hash' => hash('sha256', '0123456789'),
        'account_name' => 'DRIVER',
        'is_verified' => true,
        'is_default' => true,
    ]);
    Sanctum::actingAs($scenario['driver'], ['driver:*']);

    $created = $this->postJson('/api/v1/driver/withdrawals', [
        'bank_account_id' => $bankAccount->public_id,
        'amount' => 100_000,
    ], ['Idempotency-Key' => 'withdrawal-create-key'])->assertCreated();
    $withdrawal = WithdrawalRequest::query()
        ->where('public_id', $created->json('data.id'))
        ->firstOrFail();
    $this->assertDatabaseHas('wallets', [
        'id' => $scenario['driver_wallet']->id,
        'balance' => 300_000,
        'reserved_withdrawal_amount' => 100_000,
    ]);
    $admin = User::factory()->create();
    $adminRoleId = Role::query()->where('key', RoleKey::Admin->value)->value('id');
    $admin->roles()->attach($adminRoleId, ['granted_at' => now()]);

    app(WithdrawalService::class)->complete($withdrawal, $admin, 'BANK-TRANSFER-1');

    $this->assertDatabaseHas('withdrawal_requests', [
        'id' => $withdrawal->id,
        'status' => 'COMPLETED',
        'bank_transfer_reference' => 'BANK-TRANSFER-1',
    ]);
    $this->assertDatabaseHas('wallets', [
        'id' => $scenario['driver_wallet']->id,
        'balance' => 200_000,
        'reserved_withdrawal_amount' => 0,
    ]);
    $this->assertDatabaseCount('ledger_entries', 2);
});

test('releases a withdrawal reservation when admin rejects it', function () {
    $scenario = ExecutionScenarioBuilder::create(ServiceType::Drive);
    $scenario['driver_wallet']->forceFill([
        'balance' => 200_000,
        'reserved_withdrawal_amount' => 100_000,
    ])->save();
    $bankAccount = DriverBankAccount::query()->create([
        'driver_profile_id' => $scenario['profile']->id,
        'bank_code' => 'VCB',
        'account_number_encrypted' => '987654321',
        'account_number_hash' => hash('sha256', '987654321'),
        'account_name' => 'DRIVER',
        'is_verified' => true,
        'is_default' => true,
    ]);
    $withdrawal = WithdrawalRequest::query()->create([
        'wallet_id' => $scenario['driver_wallet']->id,
        'driver_bank_account_id' => $bankAccount->id,
        'amount' => 100_000,
        'status' => 'PENDING',
        'requested_at' => now(),
    ]);
    $admin = User::factory()->create();

    app(WithdrawalService::class)->reject($withdrawal, $admin, 'BANK_ACCOUNT_INVALID');

    $this->assertDatabaseHas('wallets', [
        'id' => $scenario['driver_wallet']->id,
        'balance' => 200_000,
        'reserved_withdrawal_amount' => 0,
    ]);
    $this->assertDatabaseCount('ledger_transactions', 0);
});

test('refunds only customer paid amount and updates payment status', function () {
    $scenario = ExecutionScenarioBuilder::create(
        ServiceType::Drive,
        PaymentMethod::Wallet,
    );
    $scenario['payment']->forceFill(['status' => PaymentStatus::Settled])->save();
    $account = LedgerAccount::query()->create([
        'owner_type' => 'USER',
        'owner_user_id' => $scenario['customer']->id,
        'code' => 'USER:'.$scenario['customer']->id.':VND',
        'account_type' => 'WALLET_LIABILITY',
        'currency' => 'VND',
        'status' => 'ACTIVE',
    ]);
    $wallet = Wallet::query()->create([
        'user_id' => $scenario['customer']->id,
        'ledger_account_id' => $account->id,
        'currency' => 'VND',
        'balance' => 0,
        'reserved_withdrawal_amount' => 0,
        'status' => 'ACTIVE',
        'version' => 1,
    ]);
    $admin = User::factory()->create();

    app(RefundService::class)->refund(
        $scenario['payment'],
        $admin,
        30_000,
        'PARTIAL_SERVICE_ISSUE',
    );
    expect($scenario['payment']->fresh()?->status)->toBe(PaymentStatus::PartiallyRefunded);

    app(RefundService::class)->refund(
        $scenario['payment'],
        $admin,
        70_000,
        'FULL_RESOLUTION',
    );

    expect($scenario['payment']->fresh()?->status)->toBe(PaymentStatus::Refunded);
    $this->assertDatabaseHas('wallets', ['id' => $wallet->id, 'balance' => 100_000]);
    $this->assertDatabaseCount('refunds', 2);
    $this->assertDatabaseCount('ledger_transactions', 2);
});
