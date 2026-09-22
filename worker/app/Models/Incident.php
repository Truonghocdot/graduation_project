<?php

namespace App\Models;

use App\Enums\IncidentStatus;
use Database\Factories\IncidentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/** @property int $id
 * @property string $public_id
 * @property int $service_request_id
 * @property int $reported_by
 * @property string $incident_type
 * @property string $severity
 * @property IncidentStatus $status
 * @property string|null $description
 * @property array<string, mixed>|null $evidence
 * @property int|null $assigned_to
 */
#[Fillable([
    'service_request_id', 'reported_by', 'incident_type', 'severity',
    'status', 'description', 'evidence', 'assigned_to',
    'resolution_code', 'resolved_at',
])]
class Incident extends Model
{
    /** @use HasFactory<IncidentFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (Incident $incident): void {
            $incident->public_id ??= (string) Str::uuid();
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

    /** @return BelongsTo<User, $this> */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    /** @return BelongsTo<User, $this> */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => IncidentStatus::class,
            'evidence' => 'array',
            'resolved_at' => 'immutable_datetime',
        ];
    }
}
