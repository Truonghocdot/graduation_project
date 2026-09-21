<?php

namespace App\Services\Execution;

use App\Contracts\Realtime\LocationPublisher;
use App\Enums\AssignmentStatus;
use App\Models\Assignment;
use App\Models\DriverLastLocation;
use App\Models\DriverProfile;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DriverLocationService
{
    public function __construct(private readonly LocationPublisher $publisher) {}

    /** @param array<string, mixed> $data */
    public function update(User $driver, array $data): DriverLastLocation
    {
        $profile = DriverProfile::query()->where('user_id', $driver->id)->firstOrFail();
        $assignment = Assignment::query()
            ->where('driver_profile_id', $profile->id)
            ->where('status', AssignmentStatus::Active->value)
            ->with('serviceRequest')
            ->firstOrFail();
        $capturedAt = CarbonImmutable::parse((string) $data['captured_at']);
        $current = DriverLastLocation::query()->find($profile->id);

        if ($current !== null && ! $capturedAt->isAfter($current->last_location_at)) {
            throw ValidationException::withMessages([
                'captured_at' => ['The location sample is older than the current snapshot.'],
            ]);
        }

        $snapshot = [
            'lat' => (float) $data['latitude'],
            'lng' => (float) $data['longitude'],
            'accuracy' => (float) $data['accuracy'],
            'heading' => isset($data['heading']) ? (float) $data['heading'] : null,
            'speed' => isset($data['speed']) ? (float) $data['speed'] : null,
        ];
        $location = DriverLastLocation::query()->updateOrCreate(
            ['driver_profile_id' => $profile->id],
            [
                'last_location' => $snapshot,
                'last_location_at' => $capturedAt,
                'updated_at' => now(),
            ],
        );
        $this->publisher->publish([
            'event_id' => (string) Str::uuid(),
            'event_type' => 'DRIVER_LOCATION_UPDATED',
            'aggregate_type' => 'SERVICE_REQUEST',
            'aggregate_id' => $assignment->service_request_id,
            'aggregate_version' => $assignment->serviceRequest->version,
            'payload' => [
                'service_request_id' => $assignment->serviceRequest->public_id,
                'driver_profile_id' => $profile->public_id,
                'location' => $snapshot,
                'captured_at' => $capturedAt->toISOString(),
            ],
            'occurred_at' => now()->toISOString(),
        ]);

        return $location;
    }
}
