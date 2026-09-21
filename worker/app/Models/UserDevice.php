<?php

namespace App\Models;

use App\Enums\AppType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'device_id',
    'app_type',
    'platform',
    'push_token',
    'last_seen_at',
    'revoked_at',
])]
class UserDevice extends Model
{
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
            'app_type' => AppType::class,
            'last_seen_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
        ];
    }
}
