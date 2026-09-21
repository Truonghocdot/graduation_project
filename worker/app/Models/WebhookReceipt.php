<?php

namespace App\Models;

use Database\Factories\WebhookReceiptFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'provider', 'provider_event_id', 'signature_valid', 'payload',
    'status', 'processed_at', 'created_at',
])]
class WebhookReceipt extends Model
{
    /** @use HasFactory<WebhookReceiptFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'signature_valid' => 'boolean',
            'payload' => 'array',
            'processed_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }
}
