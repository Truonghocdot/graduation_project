<?php

namespace App\Models;

use Database\Factories\CodAccountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** @property int $id
 * @property int $delivery_order_id
 * @property int $driver_profile_id
 * @property float $cod_amount
 * @property string $status
 * @property float $advanced_amount
 * @property float $collected_amount
 */
#[Fillable([
    'delivery_order_id', 'driver_profile_id', 'cod_amount', 'status',
    'advanced_amount', 'collected_amount', 'advanced_at',
    'collected_at', 'closed_at',
])]
class CodAccount extends Model
{
    /** @use HasFactory<CodAccountFactory> */
    use HasFactory;

    /** @return BelongsTo<DeliveryOrder, $this> */
    public function deliveryOrder(): BelongsTo
    {
        return $this->belongsTo(DeliveryOrder::class, 'delivery_order_id');
    }

    /** @return BelongsTo<DriverProfile, $this> */
    public function driverProfile(): BelongsTo
    {
        return $this->belongsTo(DriverProfile::class);
    }

    /** @return HasMany<CodTransaction, $this> */
    public function transactions(): HasMany
    {
        return $this->hasMany(CodTransaction::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'cod_amount' => 'float',
            'advanced_amount' => 'float',
            'collected_amount' => 'float',
            'advanced_at' => 'immutable_datetime',
            'collected_at' => 'immutable_datetime',
            'closed_at' => 'immutable_datetime',
        ];
    }
}
