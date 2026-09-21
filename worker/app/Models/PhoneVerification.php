<?php

namespace App\Models;

use App\Enums\PhoneVerificationPurpose;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property string $phone
 * @property PhoneVerificationPurpose $purpose
 * @property string $code_hash
 * @property int $attempt_count
 * @property int $max_attempts
 * @property CarbonImmutable $expires_at
 * @property CarbonImmutable|null $verified_at
 * @property CarbonImmutable|null $invalidated_at
 */
#[Fillable([
    'user_id',
    'phone',
    'purpose',
    'code_hash',
    'attempt_count',
    'max_attempts',
    'expires_at',
    'verified_at',
    'invalidated_at',
])]
class PhoneVerification extends Model
{
    public const UPDATED_AT = null;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'purpose' => PhoneVerificationPurpose::class,
            'expires_at' => 'immutable_datetime',
            'verified_at' => 'immutable_datetime',
            'invalidated_at' => 'immutable_datetime',
        ];
    }
}
