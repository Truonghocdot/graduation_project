<?php

namespace App\Models;

use App\Enums\RatingModerationStatus;
use Database\Factories\RatingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @property int $id
 * @property int $service_request_id
 * @property int $assignment_id
 * @property int $reviewer_user_id
 * @property int $reviewee_user_id
 * @property string $direction
 * @property int $score
 * @property array<int, string>|null $tags
 * @property string|null $comment
 * @property RatingModerationStatus $moderation_status
 */
#[Fillable([
    'service_request_id', 'assignment_id', 'reviewer_user_id',
    'reviewee_user_id', 'direction', 'score', 'tags', 'comment',
    'moderation_status',
])]
class Rating extends Model
{
    /** @use HasFactory<RatingFactory> */
    use HasFactory;

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
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function reviewee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewee_user_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'score' => 'integer',
            'tags' => 'array',
            'moderation_status' => RatingModerationStatus::class,
        ];
    }
}
