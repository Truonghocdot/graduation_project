<?php

namespace Tests\Support;

use App\Enums\AssignmentStatus;
use App\Enums\BookingType;
use App\Enums\DriverAvailabilityStatus;
use App\Enums\DriverReviewStatus;
use App\Enums\PayerType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\QuoteStatus;
use App\Enums\ReviewableStatus;
use App\Enums\RoleKey;
use App\Enums\ServiceRequestStatus;
use App\Enums\ServiceType;
use App\Enums\StopType;
use App\Models\Assignment;
use App\Models\DeliveryOrder;
use App\Models\DriverProfile;
use App\Models\LedgerAccount;
use App\Models\Payment;
use App\Models\PricingRule;
use App\Models\Quote;
use App\Models\RideBooking;
use App\Models\Role;
use App\Models\ServiceRequest;
use App\Models\ServiceStop;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleType;
use App\Models\Wallet;

class ExecutionScenarioBuilder
{
    /**
     * @return array{
     *   customer: User,
     *   driver: User,
     *   profile: DriverProfile,
     *   request: ServiceRequest,
     *   assignment: Assignment,
     *   payment: Payment,
     *   driver_wallet: Wallet
     * }
     */
    public static function create(
        ServiceType $serviceType,
        PaymentMethod $paymentMethod = PaymentMethod::Cash,
        float $voucherDiscount = 0,
        bool $isCod = false,
    ): array {
        $customer = User::factory()->create();
        $driver = User::factory()->create();
        $driverRoleId = Role::query()->where('key', RoleKey::Driver->value)->value('id');
        $driver->roles()->attach($driverRoleId, ['granted_at' => now()]);
        $vehicleType = VehicleType::factory()->create([
            'unique_key' => fake()->unique()->bothify('EXEC_####'),
            'is_active' => true,
        ]);
        $rule = PricingRule::factory()->create([
            'service_type' => $serviceType,
            'vehicle_type_id' => $vehicleType->id,
            'base_fare' => 100_000,
            'price_per_extra_km' => 10_000,
            'driver_rate' => 0.88,
            'effective_from' => now()->subDay(),
        ]);
        $quote = Quote::query()->create([
            'requested_by' => $customer->id,
            'service_type' => $serviceType,
            'vehicle_type_id' => $vehicleType->id,
            'pricing_rule_id' => $rule->id,
            'booking_type' => BookingType::Now,
            'pickup_snapshot' => ['address' => 'Pickup', 'latitude' => 10.77, 'longitude' => 106.68],
            'dropoff_snapshot' => ['address' => 'Dropoff', 'latitude' => 10.78, 'longitude' => 106.69],
            'service_payload' => $serviceType === ServiceType::Delivery
                ? [
                    'goods_type' => 'GENERAL',
                    'weight_kg' => 5,
                    'is_cod' => $isCod,
                    'cod_amount' => $isCod ? 500_000 : 0,
                ]
                : ['passenger_count' => 1],
            'route_snapshot' => ['provider' => 'fake', 'distance_meters' => 5_000, 'duration_seconds' => 900],
            'distance_meters' => 5_000,
            'duration_seconds' => 900,
            'base_fare' => 100_000,
            'extra_distance_fare' => 0,
            'surcharge_amount' => 0,
            'gross_fare' => 100_000,
            'voucher_discount' => $voucherDiscount,
            'customer_payable' => 100_000 - $voucherDiscount,
            'driver_rate' => 0.88,
            'currency' => 'VND',
            'status' => QuoteStatus::Used,
            'used_at' => now(),
            'expires_at' => now()->addMinutes(5),
        ]);
        $request = ServiceRequest::query()->create([
            'service_type' => $serviceType,
            'created_by' => $customer->id,
            'vehicle_type_id' => $vehicleType->id,
            'quote_id' => $quote->id,
            'status' => $serviceType === ServiceType::Delivery
                ? ServiceRequestStatus::DriverArrivingPickup
                : ServiceRequestStatus::DriverArriving,
            'booking_type' => BookingType::Now,
            'search_started_at' => now(),
            'version' => 2,
        ]);
        foreach ([
            StopType::Pickup->value => [10.77, 106.68],
            StopType::Dropoff->value => [10.78, 106.69],
        ] as $type => [$latitude, $longitude]) {
            ServiceStop::query()->create([
                'service_request_id' => $request->id,
                'stop_type' => $type,
                'address' => $type,
                'latitude' => $latitude,
                'longitude' => $longitude,
            ]);
        }
        $profile = DriverProfile::query()->create([
            'user_id' => $driver->id,
            'review_status' => DriverReviewStatus::Approved,
            'availability_status' => DriverAvailabilityStatus::Busy,
            'cod_limit' => 8_000_000,
        ]);
        $vehicle = Vehicle::query()->create([
            'driver_profile_id' => $profile->id,
            'vehicle_type_id' => $vehicleType->id,
            'plate_number' => strtoupper(fake()->unique()->bothify('EX-#####')),
            'status' => ReviewableStatus::Approved,
            'is_selected' => true,
        ]);
        $assignment = Assignment::query()->create([
            'service_request_id' => $request->id,
            'driver_profile_id' => $profile->id,
            'vehicle_id' => $vehicle->id,
            'status' => AssignmentStatus::Active,
            'assigned_at' => now(),
        ]);
        $driverAccount = LedgerAccount::query()->create([
            'owner_type' => 'USER',
            'owner_user_id' => $driver->id,
            'code' => 'USER:'.$driver->id.':VND',
            'account_type' => 'WALLET_LIABILITY',
            'currency' => 'VND',
            'status' => 'ACTIVE',
        ]);
        $driverWallet = Wallet::query()->create([
            'user_id' => $driver->id,
            'ledger_account_id' => $driverAccount->id,
            'currency' => 'VND',
            'balance' => 0,
            'reserved_withdrawal_amount' => 0,
            'status' => 'ACTIVE',
            'version' => 1,
        ]);
        $payment = Payment::query()->create([
            'service_request_id' => $request->id,
            'payer_type' => PayerType::Orderer,
            'payer_user_id' => $customer->id,
            'method' => $paymentMethod,
            'status' => PaymentStatus::Ready,
            'currency' => 'VND',
            'gross_fare' => 100_000,
            'voucher_discount' => $voucherDiscount,
            'customer_payable' => 100_000 - $voucherDiscount,
            'cash_collected' => 0,
            'version' => 1,
        ]);

        if ($serviceType === ServiceType::Delivery) {
            DeliveryOrder::query()->create([
                'service_request_id' => $request->id,
                'sender_user_id' => $customer->id,
                'payer_type' => PayerType::Orderer,
                'goods_type' => 'GENERAL',
                'declared_value' => 0,
                'is_cod' => $isCod,
                'cod_amount' => $isCod ? 500_000 : 0,
                'list_type' => 'ORIGINAL',
                'proof_policy' => ['pickup' => true, 'delivery' => true],
            ]);
        } else {
            RideBooking::query()->create([
                'service_request_id' => $request->id,
                'passenger_count' => 1,
                'route_version' => 1,
            ]);
        }

        return [
            'customer' => $customer,
            'driver' => $driver,
            'profile' => $profile,
            'request' => $request,
            'assignment' => $assignment,
            'payment' => $payment,
            'driver_wallet' => $driverWallet,
        ];
    }
}
