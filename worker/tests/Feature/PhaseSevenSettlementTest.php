<?php

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ServiceRequestStatus;
use App\Enums\ServiceType;
use App\Models\LedgerTransaction;
use App\Services\Finance\SettlementService;
use Database\Seeders\RoleSeeder;
use Laravel\Sanctum\Sanctum;
use Tests\Support\ExecutionScenarioBuilder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

/** @return array<string, string|float> */
function settlementTransition(string $action, float $latitude, float $longitude): array
{
    return compact('action', 'latitude', 'longitude');
}

test('settles wallet and voucher amounts without crediting voucher to driver wallet', function () {
    $scenario = ExecutionScenarioBuilder::create(
        ServiceType::Drive,
        PaymentMethod::Wallet,
        voucherDiscount: 20_000,
    );
    Sanctum::actingAs($scenario['driver'], ['driver:*']);
    $url = '/api/v1/driver/service-requests/'.$scenario['request']->public_id.'/transition';

    $this->postJson($url, settlementTransition('arrive', 10.77, 106.68), [
        'Idempotency-Key' => 'wallet-ride-arrive',
    ])->assertOk();
    $this->postJson($url, settlementTransition('start', 10.77, 106.68), [
        'Idempotency-Key' => 'wallet-ride-start',
    ])->assertOk();
    $this->postJson($url, settlementTransition('complete', 10.78, 106.69), [
        'Idempotency-Key' => 'wallet-ride-complete',
    ])->assertOk()
        ->assertJsonPath('data.status', ServiceRequestStatus::Completed->value)
        ->assertJsonPath('data.payment.status', PaymentStatus::Settled->value)
        ->assertJsonPath('data.payment.settlement.wallet_payment_amount', 80_000)
        ->assertJsonPath('data.payment.settlement.voucher_payment_amount', 20_000)
        ->assertJsonPath('data.payment.settlement.platform_fee_debited', 12_000)
        ->assertJsonPath('data.payment.settlement.driver_net_earning', 88_000);

    $this->assertDatabaseHas('wallets', [
        'id' => $scenario['driver_wallet']->id,
        'balance' => 68_000,
    ]);
    $this->assertDatabaseCount('ledger_transactions', 2);
    $this->assertDatabaseCount('ledger_entries', 4);
});

test('settlement is idempotent and every posted transaction is balanced', function () {
    $scenario = ExecutionScenarioBuilder::create(
        ServiceType::Drive,
        PaymentMethod::Wallet,
    );
    $scenario['request']->forceFill([
        'status' => ServiceRequestStatus::TripEnded,
    ])->save();
    $scenario['payment']->forceFill([
        'status' => PaymentStatus::SettlementPending,
    ])->save();

    $first = app(SettlementService::class)->settle($scenario['request']);
    $second = app(SettlementService::class)->settle($scenario['request']);

    expect($second->id)->toBe($first->id);
    $this->assertDatabaseCount('settlements', 1);
    $this->assertDatabaseCount('ledger_transactions', 2);

    foreach (LedgerTransaction::query()->with('entries')->get() as $transaction) {
        $debits = $transaction->entries->where('direction', 'DEBIT')->sum('amount');
        $credits = $transaction->entries->where('direction', 'CREDIT')->sum('amount');
        expect((float) $debits)->toBe((float) $credits);
    }
});
