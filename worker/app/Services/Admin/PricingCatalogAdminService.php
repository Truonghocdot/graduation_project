<?php

namespace App\Services\Admin;

use App\Enums\RoleKey;
use App\Models\AuditLog;
use App\Models\PricingRule;
use App\Models\ServiceArea;
use App\Models\SystemSetting;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use JsonException;

class PricingCatalogAdminService
{
    /** @param array<string, mixed> $data */
    public function createPricingRule(array $data, User $admin): PricingRule
    {
        return DB::transaction(function () use ($data, $admin): PricingRule {
            $effectiveFrom = CarbonImmutable::parse((string) $data['effective_from']);
            $currentRule = PricingRule::query()
                ->where('service_type', $data['service_type'])
                ->where('vehicle_type_id', $data['vehicle_type_id'])
                ->where('is_active', true)
                ->whereNull('effective_to')
                ->lockForUpdate()
                ->first();

            if ($currentRule !== null && $effectiveFrom->lessThanOrEqualTo($currentRule->effective_from)) {
                throw ValidationException::withMessages([
                    'effective_from' => ['A new version must start after the current version.'],
                ]);
            }

            $before = $currentRule?->attributesToArray();

            if ($currentRule !== null) {
                $currentRule->forceFill(['effective_to' => $effectiveFrom])->save();
            }

            $pricingRule = PricingRule::query()->create([
                ...$data,
                'effective_from' => $effectiveFrom,
                'effective_to' => null,
                'created_by' => $admin->id,
            ]);

            $this->audit($admin, 'PRICING_RULE_CREATED', $pricingRule, $before);

            return $pricingRule->load(['vehicleType', 'creator']);
        });
    }

    /** @param array<string, mixed> $data */
    public function updatePricingRule(PricingRule $pricingRule, array $data, User $admin): PricingRule
    {
        return DB::transaction(function () use ($pricingRule, $data, $admin): PricingRule {
            $pricingRule = PricingRule::query()->lockForUpdate()->findOrFail($pricingRule->id);

            if ($pricingRule->quotes()->exists()) {
                throw ValidationException::withMessages([
                    'pricing_rule' => ['A pricing rule used by a quote cannot be edited. Create a new version instead.'],
                ]);
            }

            $before = $pricingRule->attributesToArray();
            $pricingRule->update(collect($data)->only([
                'base_distance_km',
                'base_fare',
                'price_per_extra_km',
                'driver_rate',
                'currency',
                'is_active',
            ])->all());
            $this->audit($admin, 'PRICING_RULE_UPDATED', $pricingRule, $before);

            return $pricingRule->load(['vehicleType', 'creator']);
        });
    }

    /** @param array<string, mixed> $data */
    public function createServiceArea(array $data, User $admin): ServiceArea
    {
        $boundary = $this->decodeJson($data['boundary'] ?? null, 'boundary');

        if (! is_array($boundary)) {
            throw ValidationException::withMessages(['boundary' => ['The boundary must be a JSON object.']]);
        }

        $data['boundary'] = $boundary;
        $this->validateBoundary($data['boundary']);
        $serviceArea = ServiceArea::query()->create($data);
        $this->audit($admin, 'SERVICE_AREA_CREATED', $serviceArea);

        return $serviceArea;
    }

    /** @param array<string, mixed> $data */
    public function updateServiceArea(ServiceArea $serviceArea, array $data, User $admin): ServiceArea
    {
        $boundary = $this->decodeJson($data['boundary'] ?? null, 'boundary');

        if (! is_array($boundary)) {
            throw ValidationException::withMessages(['boundary' => ['The boundary must be a JSON object.']]);
        }

        $data['boundary'] = $boundary;
        $this->validateBoundary($data['boundary']);
        $before = $serviceArea->attributesToArray();
        $serviceArea->update($data);
        $this->audit($admin, 'SERVICE_AREA_UPDATED', $serviceArea, $before);

        return $serviceArea;
    }

    /** @param array<string, mixed> $data */
    public function createSystemSetting(array $data, User $admin): SystemSetting
    {
        $data['value'] = $this->decodeJson($data['value'] ?? null, 'value');
        $this->validateSetting((string) $data['key'], $data['value']);
        $data['updated_by'] = $admin->id;
        $setting = SystemSetting::query()->create($data);
        $this->audit($admin, 'SYSTEM_SETTING_CREATED', $setting);

        return $setting;
    }

    /** @param array<string, mixed> $data */
    public function updateSystemSetting(SystemSetting $setting, array $data, User $admin): SystemSetting
    {
        $data['value'] = $this->decodeJson($data['value'] ?? null, 'value');
        $this->validateSetting($setting->key, $data['value']);
        $data['updated_by'] = $admin->id;
        $before = $setting->attributesToArray();
        $setting->update($data);
        $this->audit($admin, 'SYSTEM_SETTING_UPDATED', $setting, $before);

        return $setting;
    }

    private function decodeJson(mixed $value, string $field): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        try {
            return json_decode($value, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw ValidationException::withMessages([$field => ['The value must be valid JSON.']]);
        }
    }

    private function validateSetting(string $key, mixed $value): void
    {
        $allowedKeys = [
            'pricing.quote_ttl_seconds',
            'pricing.rounding_unit',
            'pricing.float_tolerance',
        ];

        if (! in_array($key, $allowedKeys, true)) {
            throw ValidationException::withMessages(['key' => ['This pricing setting is not supported.']]);
        }

        if (! is_numeric($value)) {
            throw ValidationException::withMessages(['value' => ['The pricing setting must be numeric JSON.']]);
        }

        $numericValue = (float) $value;
        $valid = match ($key) {
            'pricing.quote_ttl_seconds' => $numericValue >= 60 && $numericValue <= 3_600,
            'pricing.rounding_unit' => $numericValue >= 1 && $numericValue <= 100_000,
            'pricing.float_tolerance' => $numericValue >= 0 && $numericValue <= 1,
        };

        if (! $valid) {
            throw ValidationException::withMessages(['value' => ['The pricing setting is outside its allowed range.']]);
        }
    }

    /** @param array<string, mixed> $boundary */
    private function validateBoundary(array $boundary): void
    {
        $type = $boundary['type'] ?? null;
        $valid = in_array($type, ['Polygon', 'MultiPolygon'], true)
            && isset($boundary['coordinates'])
            && is_array($boundary['coordinates']);
        $valid = $valid || ($type === 'Bounds'
            && is_array($boundary['southwest'] ?? null)
            && is_array($boundary['northeast'] ?? null));

        if (! $valid) {
            throw ValidationException::withMessages([
                'boundary' => ['Use GeoJSON Polygon/MultiPolygon or Bounds with southwest and northeast.'],
            ]);
        }
    }

    /** @param array<string, mixed>|null $before */
    private function audit(
        User $admin,
        string $action,
        Model $subject,
        ?array $before = null,
    ): void {
        $subjectId = is_numeric($subject->getKey())
            ? (int) $subject->getKey()
            : (int) hexdec(substr(hash('sha256', (string) $subject->getKey()), 0, 15));

        AuditLog::query()->create([
            'actor_user_id' => $admin->id,
            'actor_role' => RoleKey::Admin->value,
            'action' => $action,
            'subject_type' => $subject::class,
            'subject_id' => $subjectId,
            'before' => $before,
            'after' => $subject->attributesToArray(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'correlation_id' => (string) Str::uuid(),
            'created_at' => now(),
        ]);
    }
}
