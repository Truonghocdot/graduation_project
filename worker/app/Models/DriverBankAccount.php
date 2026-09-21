<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

#[Fillable([
    'driver_profile_id',
    'bank_code',
    'account_number_encrypted',
    'account_number_hash',
    'account_name',
    'is_verified',
    'is_default',
])]
class DriverBankAccount extends Model
{
    use SoftDeletes;

    protected static function booted(): void
    {
        static::creating(function (DriverBankAccount $account): void {
            $account->public_id ??= (string) Str::uuid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    /** @return BelongsTo<DriverProfile, $this> */
    public function driverProfile(): BelongsTo
    {
        return $this->belongsTo(DriverProfile::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'account_number_encrypted' => 'encrypted',
            'is_verified' => 'boolean',
            'is_default' => 'boolean',
        ];
    }
}
