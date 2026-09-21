<?php

namespace App\Models;

use App\Enums\EvidenceType;
use Carbon\CarbonImmutable;
use Database\Factories\ServiceEvidenceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/** @property int $id
 * @property string $public_id
 * @property int $service_request_id
 * @property int $assignment_id
 * @property int $uploaded_by
 * @property EvidenceType $evidence_type
 * @property string $storage_path
 * @property string $original_name
 * @property string $mime_type
 * @property int $size_bytes
 * @property string $sha256
 * @property array<string, mixed>|null $metadata
 * @property CarbonImmutable $created_at
 */
#[Fillable([
    'service_request_id', 'assignment_id', 'uploaded_by', 'evidence_type',
    'storage_path', 'original_name', 'mime_type', 'size_bytes', 'sha256',
    'metadata', 'created_at',
])]
class ServiceEvidence extends Model
{
    /** @use HasFactory<ServiceEvidenceFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected $table = 'service_evidences';

    protected static function booted(): void
    {
        static::creating(function (ServiceEvidence $evidence): void {
            $evidence->public_id ??= (string) Str::uuid();
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

    /** @return BelongsTo<User, $this> */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'evidence_type' => EvidenceType::class,
            'size_bytes' => 'integer',
            'metadata' => 'array',
            'created_at' => 'immutable_datetime',
        ];
    }
}
