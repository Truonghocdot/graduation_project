<?php

use Illuminate\Support\Facades\Schema;

test('creates the documented domain tables', function () {
    $tables = [
        'driver_profiles',
        'vehicles',
        'pricing_rules',
        'quotes',
        'service_requests',
        'delivery_orders',
        'ride_bookings',
        'driver_offers',
        'assignments',
        'wallets',
        'ledger_transactions',
        'payments',
        'settlements',
        'discount_transactions',
        'cod_accounts',
        'support_tickets',
        'outbox_events',
    ];

    $missingTables = collect($tables)
        ->reject(fn (string $table): bool => Schema::hasTable($table))
        ->values()
        ->all();

    expect($missingTables)->toBe([]);
});

test('uses phone first authentication columns', function () {
    expect(Schema::hasColumns('users', [
        'public_id',
        'phone',
        'phone_verified_at',
        'email',
        'password',
        'status',
    ]))->toBeTrue()
        ->and(Schema::hasColumn('users', 'email_verified_at'))->toBeFalse()
        ->and(Schema::hasTable('phone_password_reset_tokens'))->toBeTrue()
        ->and(Schema::hasTable('password_reset_tokens'))->toBeFalse();
});

test('keeps discounts and realtime locations outside the wallet ledger', function () {
    expect(Schema::hasColumns('discount_transactions', [
        'payment_id',
        'voucher_redemption_id',
        'amount',
        'status',
    ]))->toBeTrue()
        ->and(Schema::hasColumn('discount_transactions', 'wallet_id'))->toBeFalse()
        ->and(Schema::hasTable('driver_last_locations'))->toBeTrue()
        ->and(Schema::hasTable('driver_location_histories'))->toBeFalse();
});

test('creates database guards for active assignments', function () {
    $assignmentIndexes = collect(Schema::getIndexes('assignments'))
        ->pluck('name')
        ->all();

    expect($assignmentIndexes)->toContain(
        'assignments_one_active_per_request',
        'assignments_one_active_per_driver',
    );
});

test('creates public driver resources and submission tracking', function () {
    expect(Schema::hasColumns('driver_profiles', [
        'public_id',
        'review_status',
        'availability_status',
        'submitted_at',
    ]))->toBeTrue()
        ->and(Schema::hasColumns('driver_documents', ['public_id', 'driver_profile_id']))->toBeTrue()
        ->and(Schema::hasColumns('driver_bank_accounts', ['public_id', 'driver_profile_id']))->toBeTrue();
});
