<?php

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\RoleKey;
use App\Enums\ServiceType;
use App\Filament\Resources\Payments\Pages\ListPayments;
use App\Filament\Resources\Payments\PaymentResource;
use App\Filament\Resources\Settlements\SettlementResource;
use App\Filament\Resources\Wallets\WalletResource;
use App\Filament\Resources\WithdrawalRequests\Pages\ListWithdrawalRequests;
use App\Filament\Resources\WithdrawalRequests\WithdrawalRequestResource;
use App\Models\DriverBankAccount;
use App\Models\LedgerAccount;
use App\Models\Role;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WithdrawalRequest;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Tests\Support\ExecutionScenarioBuilder;

function actingAsFinanceAdmin(): User
{
    test()->seed(RoleSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $admin = User::factory()->create();
    $roleId = Role::query()->where('key', RoleKey::Admin->value)->value('id');
    $admin->roles()->attach($roleId, ['granted_at' => now()]);
    test()->actingAs($admin);

    return $admin;
}

test('keeps wallet settlement payment and withdrawal resources read only', function () {
    actingAsFinanceAdmin();
    $scenario = ExecutionScenarioBuilder::create(ServiceType::Drive);

    expect(WalletResource::canCreate())->toBeFalse()
        ->and(WalletResource::canEdit($scenario['driver_wallet']))->toBeFalse()
        ->and(SettlementResource::canCreate())->toBeFalse()
        ->and(PaymentResource::canCreate())->toBeFalse()
        ->and(WithdrawalRequestResource::canCreate())->toBeFalse();

    $this->get(WalletResource::getUrl('view', ['record' => $scenario['driver_wallet']]))
        ->assertOk()
        ->assertSee($scenario['driver']->name);
});

test('completes withdrawal through Filament without editing balance directly', function () {
    actingAsFinanceAdmin();
    $scenario = ExecutionScenarioBuilder::create(ServiceType::Drive);
    $scenario['driver_wallet']->forceFill([
        'balance' => 200_000,
        'reserved_withdrawal_amount' => 100_000,
    ])->save();
    $bank = DriverBankAccount::query()->create([
        'driver_profile_id' => $scenario['profile']->id,
        'bank_code' => 'MB',
        'account_number_encrypted' => '0123456789',
        'account_number_hash' => hash('sha256', '0123456789'),
        'account_name' => 'DRIVER',
        'is_verified' => true,
        'is_default' => true,
    ]);
    $withdrawal = WithdrawalRequest::query()->create([
        'wallet_id' => $scenario['driver_wallet']->id,
        'driver_bank_account_id' => $bank->id,
        'amount' => 100_000,
        'status' => 'PENDING',
        'requested_at' => now(),
    ]);

    Livewire::test(ListWithdrawalRequests::class)
        ->callTableAction('complete', $withdrawal, data: [
            'bank_transfer_reference' => 'FILAMENT-TRANSFER-1',
        ])
        ->assertHasNoTableActionErrors();

    $this->assertDatabaseHas('withdrawal_requests', [
        'id' => $withdrawal->id,
        'status' => 'COMPLETED',
    ]);
    $this->assertDatabaseHas('wallets', [
        'id' => $scenario['driver_wallet']->id,
        'balance' => 100_000,
    ]);
});

test('refunds a wallet payment through Filament with audit', function () {
    $admin = actingAsFinanceAdmin();
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
    Wallet::query()->create([
        'user_id' => $scenario['customer']->id,
        'ledger_account_id' => $account->id,
        'currency' => 'VND',
        'balance' => 0,
        'reserved_withdrawal_amount' => 0,
        'status' => 'ACTIVE',
        'version' => 1,
    ]);

    Livewire::test(ListPayments::class)
        ->callTableAction('refund', $scenario['payment'], data: [
            'amount' => 50_000,
            'reason_code' => 'ADMIN_APPROVED_REFUND',
        ])
        ->assertHasNoTableActionErrors();

    $this->assertDatabaseHas('refunds', [
        'payment_id' => $scenario['payment']->id,
        'amount' => 50_000,
        'status' => 'COMPLETED',
    ]);
    $this->assertDatabaseHas('audit_logs', [
        'actor_user_id' => $admin->id,
        'action' => 'REFUND_COMPLETED',
    ]);
});
