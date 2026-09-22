<?php

namespace App\Models;

use Database\Factories\UserNotificationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/** @property string $id
 * @property int $user_id
 * @property string $type
 * @property string $channel
 * @property array<string, mixed> $data
 * @property string $status
 */
#[Fillable([
    'id', 'user_id', 'type', 'channel', 'data', 'status',
    'sent_at', 'read_at',
])]
class UserNotification extends Model
{
    /** @use HasFactory<UserNotificationFactory> */
    use HasFactory;

    protected $table = 'notifications';

    protected $keyType = 'string';

    public $incrementing = false;

    protected static function booted(): void
    {
        static::creating(function (UserNotification $notification): void {
            $notification->id ??= (string) Str::uuid();
        });
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'sent_at' => 'immutable_datetime',
            'read_at' => 'immutable_datetime',
        ];
    }
}
