<?php

namespace App\Services\Admin;

use App\Enums\RoleKey;
use App\Models\AuditLog;
use App\Models\PricingRule;
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
                    'effective_from' => ['Phiên bản mới phải bắt đầu sau phiên bản hiện tại.'],
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
                    'pricing_rule' => ['Không thể sửa quy tắc giá đã được dùng trong báo giá. Hãy tạo phiên bản mới.'],
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

    /** @param array{quote_ttl_seconds: int|float, rounding_unit: int|float, float_tolerance: int|float, vietqr_bank_code: string, vietqr_account_number: string, vietqr_account_name: string} $data */
    public function saveSystemSettings(array $data, User $admin): void
    {
        foreach ([
            'pricing.quote_ttl_seconds' => $data['quote_ttl_seconds'],
            'pricing.rounding_unit' => $data['rounding_unit'],
            'pricing.float_tolerance' => $data['float_tolerance'],
            'finance.vietqr.bank_code' => json_encode($data['vietqr_bank_code'], JSON_UNESCAPED_UNICODE),
            'finance.vietqr.account_number' => json_encode($data['vietqr_account_number'], JSON_UNESCAPED_UNICODE),
            'finance.vietqr.account_name' => json_encode($data['vietqr_account_name'], JSON_UNESCAPED_UNICODE),
        ] as $key => $value) {
            $setting = SystemSetting::query()->whereKey($key)->first();
            $payload = [
                'key' => $key,
                'value' => $value,
                'is_public' => $setting?->is_public ?? false,
            ];

            if ($setting === null) {
                $this->createSystemSetting($payload, $admin);
            } else {
                $this->updateSystemSetting($setting, $payload, $admin);
            }
        }
    }

    private function decodeJson(mixed $value, string $field): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        try {
            return json_decode($value, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw ValidationException::withMessages([$field => ['Giá trị phải là JSON hợp lệ.']]);
        }
    }

    private function validateSetting(string $key, mixed $value): void
    {
        $allowedKeys = [
            'pricing.quote_ttl_seconds',
            'pricing.rounding_unit',
            'pricing.float_tolerance',
            'finance.vietqr.bank_code',
            'finance.vietqr.account_number',
            'finance.vietqr.account_name',
        ];

        if (! in_array($key, $allowedKeys, true)) {
            throw ValidationException::withMessages(['key' => ['Thiết lập giá này không được hỗ trợ.']]);
        }

        if (str_starts_with($key, 'finance.vietqr.')) {
            if (! is_string($value) || trim($value) === '') {
                throw ValidationException::withMessages(['value' => ['Cấu hình VietQR không được để trống.']]);
            }

            if (mb_strlen(trim($value)) > 120) {
                throw ValidationException::withMessages(['value' => ['Cấu hình VietQR vượt quá độ dài cho phép.']]);
            }

            return;
        }

        if (! is_numeric($value)) {
            throw ValidationException::withMessages(['value' => ['Thiết lập giá phải là JSON dạng số.']]);
        }

        $numericValue = (float) $value;
        $valid = match ($key) {
            'pricing.quote_ttl_seconds' => $numericValue >= 60 && $numericValue <= 3_600,
            'pricing.rounding_unit' => $numericValue >= 1 && $numericValue <= 100_000,
            'pricing.float_tolerance' => $numericValue >= 0 && $numericValue <= 1,
        };

        if (! $valid) {
            throw ValidationException::withMessages(['value' => ['Thiết lập giá nằm ngoài phạm vi cho phép.']]);
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
