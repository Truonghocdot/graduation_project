<?php

use App\Enums\BookingType;
use App\Enums\DiscountType;
use App\Enums\PayerType;
use App\Enums\PaymentMethod;
use App\Enums\QuoteStatus;
use App\Enums\ServiceRequestStatus;
use App\Enums\ServiceType;
use App\Enums\UserStatus;
use App\Models\LedgerAccount;
use App\Models\PricingRule;
use App\Models\Quote;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Models\VehicleType;
use App\Models\Voucher;
use App\Models\Wallet;
use Laravel\Sanctum\Sanctum;

/** @return array{user: User, quote: Quote, wallet: Wallet} */
function phaseFourSetup(
    ServiceType $serviceType = ServiceType::Delivery,
    float $walletBalance = 100_000,
    BookingType $bookingType = BookingType::Now,
): array {
    $user = User::factory()->create();
    $vehicleType = VehicleType::factory()->create([
        'unique_key' => $serviceType === ServiceType::Delivery ? 'MOTORBIKE' : 'CAR_4_SEAT',
        'passenger_capacity' => 4,
        'max_weight_kg' => 30,
        'is_active' => true,
    ]);
    $pricingRule = PricingRule::factory()->create([
        'service_type' => $serviceType,
        'vehicle_type_id' => $vehicleType->id,
        'base_distance_km' => 3,
        'base_fare' => $serviceType === ServiceType::Delivery ? 18_000 : 30_000,
        'price_per_extra_km' => 5_000,
        'driver_rate' => 0.88,
        'effective_from' => now()->subDay(),
        'effective_to' => null,
        'is_active' => true,
    ]);
    $account = LedgerAccount::query()->create([
        'owner_type' => 'USER',
        'owner_user_id' => $user->id,
        'code' => 'USER:'.$user->id.':VND',
        'account_type' => 'WALLET_LIABILITY',
        'currency' => 'VND',
        'status' => 'ACTIVE',
    ]);
    $wallet = Wallet::query()->create([
        'user_id' => $user->id,
        'ledger_account_id' => $account->id,
        'currency' => 'VND',
        'balance' => $walletBalance,
        'reserved_withdrawal_amount' => 0,
        'status' => 'ACTIVE',
        'version' => 1,
    ]);
    $quote = Quote::query()->create([
        'requested_by' => $user->id,
        'service_type' => $serviceType,
        'vehicle_type_id' => $vehicleType->id,
        'pricing_rule_id' => $pricingRule->id,
        'booking_type' => $bookingType,
        'scheduled_at' => $bookingType === BookingType::Scheduled ? now()->addHour() : null,
        'pickup_snapshot' => ['address' => 'Pickup', 'latitude' => 10.77, 'longitude' => 106.68],
        'dropoff_snapshot' => ['address' => 'Dropoff', 'latitude' => 10.78, 'longitude' => 106.69],
        'service_payload' => $serviceType === ServiceType::Delivery
            ? ['goods_type' => 'GENERAL', 'weight_kg' => 5, 'declared_value' => 0, 'is_cod' => false, 'cod_amount' => 0]
            : ['passenger_count' => 2],
        'route_snapshot' => ['provider' => 'fake', 'distance_meters' => 2_000, 'duration_seconds' => 600],
        'distance_meters' => 2_000,
        'duration_seconds' => 600,
        'base_fare' => $serviceType === ServiceType::Delivery ? 18_000 : 30_000,
        'extra_distance_fare' => 0,
        'surcharge_amount' => 0,
        'gross_fare' => $serviceType === ServiceType::Delivery ? 18_000 : 30_000,
        'voucher_discount' => 0,
        'customer_payable' => $serviceType === ServiceType::Delivery ? 18_000 : 30_000,
        'driver_rate' => 0.88,
        'currency' => 'VND',
        'status' => QuoteStatus::Active,
        'expires_at' => now()->addMinutes(5),
    ]);

    return ['user' => $user, 'quote' => $quote, 'wallet' => $wallet];
}

function phaseFourDeliveryPayload(Quote $quote, PaymentMethod $method = PaymentMethod::Wallet): array
{
    return [
        'quote_id' => $quote->public_id,
        'payment_method' => $method->value,
        'payer_type' => PayerType::Orderer->value,
        'stops' => [
            'pickup' => ['contact_name' => 'Sender', 'contact_phone' => '0900000001'],
            'dropoff' => ['contact_name' => 'Receiver', 'contact_phone' => '0900000002'],
        ],
    ];
}

test('creates a delivery order with one atomic wallet debit and idempotent retry', function () {
    $setup = phaseFourSetup();
    Sanctum::actingAs($setup['user'], ['customer:*']);
    $payload = phaseFourDeliveryPayload($setup['quote']);

    $first = $this->postJson('/api/v1/delivery/orders', $payload, ['Idempotency-Key' => 'delivery-key-1'])
        ->assertCreated();
    $second = $this->postJson('/api/v1/delivery/orders', $payload, ['Idempotency-Key' => 'delivery-key-1'])
        ->assertCreated();

    expect($second->json('data.id'))->toBe($first->json('data.id'))
        ->and($first->json('data.status'))->toBe(ServiceRequestStatus::SearchingDriver->value)
        ->and($first->json('data.payment.method'))->toBe(PaymentMethod::Wallet->value)
        ->and($first->json('data.delivery_order.payer_type'))->toBe(PayerType::Orderer->value);
    $this->assertDatabaseCount('service_requests', 1);
    $this->assertDatabaseCount('payments', 1);
    $this->assertDatabaseCount('ledger_transactions', 1);
    $this->assertDatabaseCount('ledger_entries', 2);
    $this->assertDatabaseHas('wallets', ['id' => $setup['wallet']->id, 'balance' => 82_000]);
    $this->assertDatabaseHas('quotes', ['id' => $setup['quote']->id, 'status' => QuoteStatus::Used->value]);
});

test('creates a drive booking with CASH without debiting a wallet', function () {
    $setup = phaseFourSetup(ServiceType::Drive, 0);
    Sanctum::actingAs($setup['user'], ['customer:*']);

    $response = $this->postJson('/api/v1/rides/bookings', [
        'quote_id' => $setup['quote']->public_id,
        'payment_method' => PaymentMethod::Cash->value,
    ], ['Idempotency-Key' => 'ride-cash-key']);

    $response->assertCreated();
    expect($response->json('data.status'))->toBe(ServiceRequestStatus::SearchingDriver->value)
        ->and($response->json('data.ride_booking.passenger_count'))->toBe(2)
        ->and($response->json('data.payment.status'))->toBe('READY');
    $this->assertDatabaseCount('ledger_transactions', 0);
    $this->assertDatabaseHas('wallets', ['id' => $setup['wallet']->id, 'balance' => 0]);
});

test('creates a scheduled booking and does not start matching yet', function () {
    $setup = phaseFourSetup(ServiceType::Drive, 100_000, BookingType::Scheduled);
    Sanctum::actingAs($setup['user'], ['customer:*']);

    $response = $this->postJson('/api/v1/rides/bookings', [
        'quote_id' => $setup['quote']->public_id,
        'payment_method' => PaymentMethod::Wallet->value,
    ], ['Idempotency-Key' => 'scheduled-ride-key']);

    $response->assertCreated();
    expect($response->json('data.status'))->toBe(ServiceRequestStatus::Scheduled->value)
        ->and($response->json('data.search_started_at'))->toBeNull();
    $this->assertDatabaseHas('outbox_events', ['event_type' => 'RIDE_BOOKING_CREATED']);
});

test('does not create a booking when wallet balance is insufficient', function () {
    $setup = phaseFourSetup(ServiceType::Delivery, 1_000);
    Sanctum::actingAs($setup['user'], ['customer:*']);

    $this->postJson('/api/v1/delivery/orders', phaseFourDeliveryPayload($setup['quote']), [
        'Idempotency-Key' => 'insufficient-wallet-key',
    ])->assertUnprocessable()->assertJsonValidationErrors('payment_method');

    $this->assertDatabaseCount('service_requests', 0);
    $this->assertDatabaseCount('payments', 0);
    $this->assertDatabaseCount('ledger_transactions', 0);
    $this->assertDatabaseHas('quotes', ['id' => $setup['quote']->id, 'status' => QuoteStatus::Active->value]);
    $this->assertDatabaseHas('wallets', ['id' => $setup['wallet']->id, 'balance' => 1_000]);
});

test('redeems the quoted voucher once and restores it with a wallet refund on cancellation', function () {
    $setup = phaseFourSetup();
    $voucher = Voucher::query()->create([
        'code' => 'PHASE4',
        'name' => 'Phase 4 voucher',
        'discount_type' => DiscountType::Fixed,
        'discount_value' => 5_000,
        'minimum_order_amount' => 0,
        'used_count' => 0,
        'starts_at' => now()->subHour(),
        'ends_at' => now()->addHour(),
        'is_active' => true,
        'created_by' => $setup['user']->id,
    ]);
    $setup['quote']->forceFill([
        'voucher_discount' => 5_000,
        'customer_payable' => 13_000,
        'service_payload' => [
            ...$setup['quote']->service_payload,
            'voucher' => ['id' => $voucher->public_id, 'code' => $voucher->code],
        ],
    ])->save();
    Sanctum::actingAs($setup['user'], ['customer:*']);
    $headers = ['Idempotency-Key' => 'voucher-delivery-key'];

    $created = $this->postJson('/api/v1/delivery/orders', phaseFourDeliveryPayload($setup['quote']), $headers)
        ->assertCreated();
    $serviceRequest = ServiceRequest::query()->where('public_id', $created->json('data.id'))->firstOrFail();
    expect($serviceRequest->payment?->customer_payable)->toBe(13_000.0);
    $this->assertDatabaseHas('vouchers', ['id' => $voucher->id, 'used_count' => 1]);
    $this->assertDatabaseCount('voucher_redemptions', 1);
    $this->assertDatabaseCount('discount_transactions', 1);

    $this->postJson('/api/v1/service-requests/'.$serviceRequest->public_id.'/cancel', [
        'reason_code' => 'CUSTOMER_CHANGED_MIND',
    ], ['Idempotency-Key' => 'voucher-cancel-key'])->assertOk();
    $this->postJson('/api/v1/service-requests/'.$serviceRequest->public_id.'/cancel', [
        'reason_code' => 'CUSTOMER_CHANGED_MIND',
    ], ['Idempotency-Key' => 'voucher-cancel-key'])->assertOk();

    $this->assertDatabaseHas('service_requests', [
        'id' => $serviceRequest->id,
        'status' => ServiceRequestStatus::Cancelled->value,
    ]);
    $this->assertDatabaseHas('vouchers', ['id' => $voucher->id, 'used_count' => 0]);
    $this->assertDatabaseHas('voucher_redemptions', ['service_request_id' => $serviceRequest->id, 'status' => 'RESTORED']);
    $this->assertDatabaseCount('ledger_transactions', 2);
    $this->assertDatabaseHas('wallets', ['id' => $setup['wallet']->id, 'balance' => 100_000]);
});

test('rejects another user from using a quote and requires an idempotency key', function () {
    $setup = phaseFourSetup();
    Sanctum::actingAs(User::factory()->create(), ['customer:*']);

    $this->postJson('/api/v1/delivery/orders', phaseFourDeliveryPayload($setup['quote']), [
        'Idempotency-Key' => 'foreign-quote-key',
    ])->assertNotFound();

    Sanctum::actingAs($setup['user'], ['customer:*']);
    $this->postJson('/api/v1/delivery/orders', phaseFourDeliveryPayload($setup['quote']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('idempotency_key')
        ->assertJsonPath(
            'errors.idempotency_key.0',
            'Header Idempotency-Key hợp lệ là bắt buộc.',
        );
});

test('does not allow cancellation after the request has moved outside pre-assignment states', function () {
    $setup = phaseFourSetup();
    Sanctum::actingAs($setup['user'], ['customer:*']);
    $created = $this->postJson('/api/v1/delivery/orders', phaseFourDeliveryPayload($setup['quote']), [
        'Idempotency-Key' => 'cancel-state-key',
    ])->assertCreated();
    $request = ServiceRequest::query()->where('public_id', $created->json('data.id'))->firstOrFail();
    $request->forceFill(['status' => ServiceRequestStatus::Assigned])->save();

    $this->postJson('/api/v1/service-requests/'.$request->public_id.'/cancel', [
        'reason_code' => 'TOO_LATE',
    ], ['Idempotency-Key' => 'cancel-too-late-key'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('service_request');
    $this->assertDatabaseHas('wallets', ['id' => $setup['wallet']->id, 'balance' => 82_000]);
});

test('does not reveal another customer service request during cancellation', function () {
    $setup = phaseFourSetup();
    Sanctum::actingAs($setup['user'], ['customer:*']);
    $created = $this->postJson('/api/v1/delivery/orders', phaseFourDeliveryPayload($setup['quote']), [
        'Idempotency-Key' => 'owner-create-key',
    ])->assertCreated();

    Sanctum::actingAs(User::factory()->create(), ['customer:*']);
    $this->postJson('/api/v1/service-requests/'.$created->json('data.id').'/cancel', [
        'reason_code' => 'NOT_OWNER',
    ], ['Idempotency-Key' => 'foreign-cancel-key'])->assertNotFound();
});

test('forbids a suspended customer from creating a service request', function () {
    $setup = phaseFourSetup();
    $setup['user']->forceFill(['status' => UserStatus::Suspended])->save();
    Sanctum::actingAs($setup['user'], ['customer:*']);

    $this->postJson('/api/v1/delivery/orders', phaseFourDeliveryPayload($setup['quote']), [
        'Idempotency-Key' => 'suspended-customer-key',
    ])->assertForbidden();

    $this->assertDatabaseCount('service_requests', 0);
});
