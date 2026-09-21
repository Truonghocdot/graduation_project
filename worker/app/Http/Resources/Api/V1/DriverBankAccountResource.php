<?php

namespace App\Http\Resources\Api\V1;

use App\Models\DriverBankAccount;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/** @mixin DriverBankAccount */
class DriverBankAccountResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $number = $this->account_number_encrypted;

        return [
            'id' => $this->public_id,
            'bank_code' => $this->bank_code,
            'account_number_masked' => Str::mask(
                $number,
                '*',
                0,
                max(0, mb_strlen($number) - 4),
            ),
            'account_name' => $this->account_name,
            'is_verified' => $this->is_verified,
            'is_default' => $this->is_default,
        ];
    }
}
