<?php

namespace App\Services\Support;

use App\Enums\RatingModerationStatus;
use App\Enums\ServiceRequestStatus;
use App\Models\Assignment;
use App\Models\OutboxEvent;
use App\Models\Rating;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Services\Notification\NotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RatingService
{
    public function __construct(private readonly NotificationService $notifications) {}

    /** @param array<string, mixed> $data */
    public function submit(
        User $user,
        ServiceRequest $serviceRequest,
        array $data,
    ): Rating {
        return DB::transaction(function () use ($user, $serviceRequest, $data): Rating {
            $request = ServiceRequest::query()->lockForUpdate()->findOrFail($serviceRequest->id);

            if ($request->status !== ServiceRequestStatus::Completed) {
                throw ValidationException::withMessages([
                    'rating' => ['Only completed services can be rated.'],
                ]);
            }

            $assignment = Assignment::query()
                ->where('service_request_id', $request->id)
                ->with('driverProfile.user')
                ->latest('id')
                ->firstOrFail();
            $isCustomer = $request->created_by === $user->id;
            $isDriver = $assignment->driverProfile->user_id === $user->id;

            if (! $isCustomer && ! $isDriver) {
                abort(404);
            }

            $reviewee = $isCustomer
                ? $assignment->driverProfile->user
                : $request->creator;
            $direction = $isCustomer ? 'CUSTOMER_TO_DRIVER' : 'DRIVER_TO_CUSTOMER';

            if (Rating::query()
                ->where('service_request_id', $request->id)
                ->where('reviewer_user_id', $user->id)
                ->where('direction', $direction)
                ->exists()) {
                throw ValidationException::withMessages([
                    'rating' => ['This service has already been rated in this direction.'],
                ]);
            }

            $rating = Rating::query()->create([
                'service_request_id' => $request->id,
                'assignment_id' => $assignment->id,
                'reviewer_user_id' => $user->id,
                'reviewee_user_id' => $reviewee->id,
                'direction' => $direction,
                'score' => $data['score'],
                'tags' => $data['tags'] ?? null,
                'comment' => $data['comment'] ?? null,
                'moderation_status' => (int) $data['score'] <= 2
                    ? RatingModerationStatus::Flagged
                    : RatingModerationStatus::Visible,
            ]);
            OutboxEvent::query()->create([
                'event_type' => (int) $data['score'] <= 2
                    ? 'LOW_RATING_FLAGGED'
                    : 'RATING_SUBMITTED',
                'aggregate_type' => 'SERVICE_REQUEST',
                'aggregate_id' => $request->id,
                'aggregate_version' => $request->version,
                'payload' => [
                    'service_request_id' => $request->public_id,
                    'rating_id' => $rating->id,
                    'direction' => $direction,
                    'score' => $rating->score,
                ],
                'status' => 'PENDING',
                'attempt_count' => 0,
                'available_at' => now(),
            ]);
            $this->notifications->create(
                $reviewee,
                'RATING_RECEIVED',
                [
                    'service_request_id' => $request->public_id,
                    'rating_id' => $rating->id,
                    'score' => $rating->score,
                ],
                $request->id,
                $request->version,
            );

            return $rating->load(['reviewer', 'reviewee']);
        });
    }
}
