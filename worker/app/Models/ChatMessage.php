<?php

namespace App\Models;

use Database\Factories\ChatMessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/** @property int $id
 * @property string $public_id
 * @property int $chat_conversation_id
 * @property int $sender_user_id
 * @property string $client_message_id
 * @property string $message_type
 * @property string|null $body
 */
#[Fillable([
    'chat_conversation_id', 'sender_user_id', 'client_message_id',
    'message_type', 'body', 'attachment_path', 'sent_at',
    'read_at', 'created_at',
])]
class ChatMessage extends Model
{
    /** @use HasFactory<ChatMessageFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        static::creating(function (ChatMessage $message): void {
            $message->public_id ??= (string) Str::uuid();
        });
    }

    /** @return BelongsTo<ChatConversation, $this> */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(ChatConversation::class, 'chat_conversation_id');
    }

    /** @return BelongsTo<User, $this> */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_user_id');
    }

    protected function casts(): array
    {
        return [
            'sent_at' => 'immutable_datetime',
            'read_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }
}
