<?php

namespace App\Services\Quote;

use App\Contracts\Maps\MapProvider;
use App\Data\Maps\Coordinates;
use App\Enums\BookingType;
use App\Enums\QuoteStatus;
use App\Enums\ServiceType;
use App\Models\Quote;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\VehicleType;
use App\Services\Maps\ServiceAreaService;
use App\Services\Pricing\PricingService;
use App\Services\Pricing\VoucherPreviewService;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

class QuoteService
{
    public function __construct(
        private readonly MapProvider $mapProvider,
        private readonly ServiceAreaService $serviceAreaService,
        private readonly PricingService $pricingService,
        private readonly VoucherPreviewService $voucherPreviewService,
    ) {}

    /** @param array<string, mixed> $data */
    public function create(User $user, array $data): Quote
    {
        $serviceType = ServiceType::from((string) $data['service_type']);
        $bookingType = BookingType::from((string) $data['booking_type']);
        $vehicleType = VehicleType::query()
            ->where('public_id', $data['vehicle_type_id'])
            ->where('is_active', true)
            ->firstOrFail();
        $pickup = $this->coordinates($data['pickup']);
        $dropoff = $this->coordinates($data['dropoff']);
        /** @var array<string, mixed> $servicePayload */
        $servicePayload = $data['service_payload'];

        $this->validateVehicleCapacity($serviceType, $vehicleType, $servicePayload);
        $serviceArea = $this->serviceAreaService->assertRouteAvailable(
            $serviceType,
            $pickup,
            $dropoff,
        );
        $route = $this->mapProvider->route($pickup, $dropoff, $vehicleType->unique_key);
        $pricingRule = $this->pricingService->currentRule($serviceType, $vehicleType);
        $subtotal = $this->pricingService->calculate($pricingRule, $route->distanceMeters);
        $voucherPreview = $this->voucherPreviewService->preview(
            isset($data['voucher_code']) ? (string) $data['voucher_code'] : null,
            $user,
            $serviceType,
            $subtotal->grossFare,
        );
        $pricing = $this->pricingService->calculate(
            $pricingRule,
            $route->distanceMeters,
            $voucherPreview['discount'],
        );

        if ($voucherPreview['voucher'] !== null) {
            $servicePayload['voucher'] = [
                'id' => $voucherPreview['voucher']->public_id,
                'code' => $voucherPreview['voucher']->code,
            ];
        }

        $routeSnapshot = $route->toArray();
        $routeSnapshot['service_area'] = [
            'id' => $serviceArea->id,
            'name' => $serviceArea->name,
        ];

        $quote = Quote::query()->create([
            'requested_by' => $user->id,
            'service_type' => $serviceType,
            'vehicle_type_id' => $vehicleType->id,
            'pricing_rule_id' => $pricingRule->id,
            'booking_type' => $bookingType,
            'scheduled_at' => $bookingType === BookingType::Scheduled
                ? CarbonImmutable::parse((string) $data['scheduled_at'])
                : null,
            'pickup_snapshot' => $this->locationSnapshot($data['pickup']),
            'dropoff_snapshot' => $this->locationSnapshot($data['dropoff']),
            'service_payload' => $servicePayload,
            'route_snapshot' => $routeSnapshot,
            'distance_meters' => $route->distanceMeters,
            'duration_seconds' => $route->durationSeconds,
            ...$pricing->toArray(),
            'status' => QuoteStatus::Active,
            'expires_at' => now()->addSeconds($this->quoteTtlSeconds()),
        ]);

        return $quote->load(['vehicleType', 'pricingRule']);
    }

    private function coordinates(mixed $location): Coordinates
    {
        /** @var array<string, mixed> $location */
        return new Coordinates(
            latitude: (float) $location['latitude'],
            longitude: (float) $location['longitude'],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function locationSnapshot(mixed $location): array
    {
        /** @var array<string, mixed> $location */
        return array_filter([
            'address' => (string) $location['address'],
            'latitude' => (float) $location['latitude'],
            'longitude' => (float) $location['longitude'],
            'note' => isset($location['note']) ? (string) $location['note'] : null,
        ], fn (mixed $value): bool => $value !== null);
    }

    /** @param array<string, mixed> $payload */
    private function validateVehicleCapacity(
        ServiceType $serviceType,
        VehicleType $vehicleType,
        array $payload,
    ): void {
        $errors = [];

        if ($serviceType === ServiceType::Drive
            && $vehicleType->passenger_capacity !== null
            && (int) $payload['passenger_count'] > $vehicleType->passenger_capacity) {
            $errors['service_payload.passenger_count'][] = 'Passenger count exceeds vehicle capacity.';
        }

        if ($serviceType === ServiceType::Delivery) {
            $limits = [
                'weight_kg' => $vehicleType->max_weight_kg,
                'length_cm' => $vehicleType->max_length_cm,
                'width_cm' => $vehicleType->max_width_cm,
                'height_cm' => $vehicleType->max_height_cm,
            ];

            foreach ($limits as $field => $limit) {
                if ($limit !== null && isset($payload[$field]) && (float) $payload[$field] > $limit) {
                    $errors["service_payload.{$field}"][] = "The value exceeds this vehicle's limit.";
                }
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function quoteTtlSeconds(): int
    {
        $setting = SystemSetting::query()
            ->where('key', 'pricing.quote_ttl_seconds')
            ->first();
        $configuredTtl = $setting !== null
            ? $setting->value
            : config('pricing.quote_ttl_seconds', 300);
        $ttl = is_numeric($configuredTtl) ? (int) $configuredTtl : 300;

        return max(60, min($ttl, 3_600));
    }
}
