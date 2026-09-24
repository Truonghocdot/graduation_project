<?php

namespace App\Services\Finance;

use App\Models\SystemSetting;

final class VietQrConfiguration
{
    /** @return array{bank_code: string, account_number: string, account_name: string} */
    public function values(): array
    {
        return [
            'bank_code' => $this->value('finance.vietqr.bank_code', (string) config('finance.vietqr.bank_code', 'MB')),
            'account_number' => $this->value('finance.vietqr.account_number', (string) config('finance.vietqr.account_number', '')),
            'account_name' => $this->value('finance.vietqr.account_name', (string) config('finance.vietqr.account_name', '')),
        ];
    }

    public function imageUrl(float $amount, string $reference): ?string
    {
        $values = $this->values();

        if ($values['bank_code'] === '' || $values['account_number'] === '') {
            return null;
        }

        return 'https://img.vietqr.io/image/'
            .rawurlencode($values['bank_code']).'-'.rawurlencode($values['account_number']).'-compact2.png?'
            .http_build_query([
                'amount' => (int) round($amount),
                'addInfo' => $reference,
                'accountName' => $values['account_name'],
            ], '', '&', PHP_QUERY_RFC3986);
    }

    private function value(string $key, string $fallback): string
    {
        $setting = SystemSetting::query()->find($key);
        $value = $setting?->value;

        return is_scalar($value) && trim((string) $value) !== ''
            ? trim((string) $value)
            : $fallback;
    }
}
