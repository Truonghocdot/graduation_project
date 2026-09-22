<?php

namespace App\Models;

use Database\Factories\ChatConversationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/** @property int $id
 * @property string $public_id
 * @property int $service_request_id
 * @property int $assignment_id
 * @property int $customer_user_id
 * @property int $driver_user_id
 * @property string $status
 */
#[Fillable([
    'service_request_id', 'assignment_id', 'customer_user_id',
    'driver_user_id', 'status', 'closed_at',
])]
class ChatConversation extends Model
{
    /** @use HasFactory<ChatConversationFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (ChatConversation $conversation): void {
            $conversation->public_id ??= (string) Str::uuid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    /** @return BelongsTo<ServiceRequest, $this> */
    public function serviceRequest(): BelongsTo
    {
        return $this->belongsTo(ServiceRequest::class);
    }

    /** @return BelongsTo<Assignment, $this> */
    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }

    /** @return HasMany<ChatMessage, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class);
    }

    protected function casts(): array
    {
        return ['closed_at' => 'immutable_datetime'];
    }
}
