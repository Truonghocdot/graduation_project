<?php

namespace App\Models;

use App\Enums\PayerType;
use Database\Factories\DeliveryOrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @property PayerType $payer_type */
#[Fillable([
    'service_request_id',
    'sender_user_id',
    'recipient_user_id',
    'payer_type',
    'goods_type',
    'goods_description',
    'weight_kg',
    'length_cm',
    'width_cm',
    'height_cm',
    'declared_value',
    'is_cod',
    'cod_amount',
    'list_type',
    'proof_policy',
])]
class DeliveryOrder extends Model
{
    /** @use HasFactory<DeliveryOrderFactory> */
    use HasFactory;

    protected $primaryKey = 'service_request_id';

    public $incrementing = false;

    protected $keyType = 'int';

    /** @return BelongsTo<ServiceRequest, $this> */
    public function serviceRequest(): BelongsTo
    {
        return $this->belongsTo(ServiceRequest::class);
    }

    /** @return BelongsTo<User, $this> */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_user_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'payer_type' => PayerType::class,
            'weight_kg' => 'float',
            'length_cm' => 'float',
            'width_cm' => 'float',
            'height_cm' => 'float',
            'declared_value' => 'float',
            'is_cod' => 'boolean',
            'cod_amount' => 'float',
            'proof_policy' => 'array',
        ];
    }
}
