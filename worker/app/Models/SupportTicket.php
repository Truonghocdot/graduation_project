<?php

namespace App\Models;

use App\Enums\SupportPriority;
use App\Enums\SupportTicketStatus;
use Database\Factories\SupportTicketFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/** @property int $id
 * @property string $public_id
 * @property int $opened_by
 * @property int|null $service_request_id
 * @property string $category
 * @property SupportPriority $priority
 * @property SupportTicketStatus $status
 * @property string $subject
 * @property string $description
 * @property int|null $assigned_to
 * @property int $version
 */
#[Fillable([
    'opened_by', 'service_request_id', 'category', 'priority', 'status',
    'subject', 'description', 'assigned_to', 'resolution_code',
    'resolution_note', 'resolved_at', 'closed_at', 'version',
])]
class SupportTicket extends Model
{
    /** @use HasFactory<SupportTicketFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (SupportTicket $ticket): void {
            $ticket->public_id ??= (string) Str::uuid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    /** @return BelongsTo<User, $this> */
    public function opener(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    /** @return BelongsTo<User, $this> */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /** @return BelongsTo<ServiceRequest, $this> */
    public function serviceRequest(): BelongsTo
    {
        return $this->belongsTo(ServiceRequest::class);
    }

    /** @return HasMany<SupportTicketMessage, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(SupportTicketMessage::class);
    }

    /** @return HasMany<TicketAttachment, $this> */
    public function attachments(): HasMany
    {
        return $this->hasMany(TicketAttachment::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'priority' => SupportPriority::class,
            'status' => SupportTicketStatus::class,
            'resolved_at' => 'immutable_datetime',
            'closed_at' => 'immutable_datetime',
            'version' => 'integer',
        ];
    }
}
