<?php

namespace App\Http\Resources\Api\V1;

use App\Models\WalletTopup;
use App\Services\Finance\VietQrConfiguration;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin WalletTopup */
class WalletTopupResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'amount' => $this->amount,
            'status' => $this->status,
            'vietqr_reference' => $this->vietqr_reference,
            'vietqr_payload' => $this->vietqr_payload,
            'vietqr_image_url' => app(VietQrConfiguration::class)->imageUrl(
                (float) $this->amount,
                (string) $this->vietqr_reference,
            ),
            'expires_at' => $this->expires_at,
            'completed_at' => $this->completed_at,
            'created_at' => $this->created_at,
        ];
    }
}
