<?php

namespace App\Services\Support;

use App\Enums\DriverAvailabilityStatus;
use App\Enums\IncidentStatus;
use App\Enums\RatingModerationStatus;
use App\Enums\RoleKey;
use App\Enums\SupportTicketStatus;
use App\Enums\UserStatus;
use App\Models\AuditLog;
use App\Models\Incident;
use App\Models\OutboxEvent;
use App\Models\Rating;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Models\User;
use App\Services\Notification\NotificationService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SupportAdminService
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function assignTicket(
        SupportTicket $ticket,
        User $staff,
        User $assignee,
    ): SupportTicket {
        $this->assertStaff($staff);
        $this->assertStaff($assignee);

        return DB::transaction(function () use ($ticket, $staff, $assignee): SupportTicket {
            $ticket = SupportTicket::query()->lockForUpdate()->findOrFail($ticket->id);
            $before = $ticket->attributesToArray();
            $ticket->forceFill([
                'assigned_to' => $assignee->id,
                'status' => SupportTicketStatus::InReview,
                'version' => $ticket->version + 1,
            ])->save();
            $this->audit($staff, 'SUPPORT_TICKET_ASSIGNED', $ticket, $before);
            $this->notifications->create(
                $assignee,
                'SUPPORT_TICKET_ASSIGNED',
                ['ticket_id' => $ticket->public_id],
                $ticket->id,
                $ticket->version,
            );

            return $ticket->load(['opener', 'assignee']);
        });
    }

    public function resolveTicket(
        SupportTicket $ticket,
        User $staff,
        string $resolutionCode,
        string $resolutionNote,
    ): SupportTicket {
        $this->assertStaff($staff);

        return DB::transaction(function () use (
            $ticket,
            $staff,
            $resolutionCode,
            $resolutionNote,
        ): SupportTicket {
            $ticket = SupportTicket::query()->lockForUpdate()->findOrFail($ticket->id);
            $this->assertAssignedOrAdmin($staff, $ticket->assigned_to);

            if (in_array($ticket->status, [
                SupportTicketStatus::Resolved,
                SupportTicketStatus::Closed,
            ], true)) {
                throw ValidationException::withMessages([
                    'ticket' => ['The ticket has already been resolved.'],
                ]);
            }

            $before = $ticket->attributesToArray();
            $ticket->forceFill([
                'status' => SupportTicketStatus::Resolved,
                'resolution_code' => $resolutionCode,
                'resolution_note' => $resolutionNote,
                'resolved_at' => now(),
                'version' => $ticket->version + 1,
            ])->save();
            SupportTicketMessage::query()->create([
                'support_ticket_id' => $ticket->id,
                'sender_user_id' => $staff->id,
                'message_type' => 'SYSTEM',
                'body' => $resolutionNote,
                'created_at' => now(),
            ]);
            $this->audit(
                $staff,
                'SUPPORT_TICKET_RESOLVED',
                $ticket,
                $before,
                $resolutionCode,
            );
            $this->outbox('SUPPORT_TICKET_RESOLVED', $ticket->id, $ticket->version, [
                'ticket_id' => $ticket->public_id,
                'status' => $ticket->status->value,
            ]);
            $this->notifications->create(
                $ticket->opener,
                'SUPPORT_TICKET_RESOLVED',
                [
                    'ticket_id' => $ticket->public_id,
                    'resolution_code' => $resolutionCode,
                ],
                $ticket->id,
                $ticket->version,
            );

            return $ticket->load(['opener', 'assignee', 'messages.sender']);
        });
    }

    public function resolveIncident(
        Incident $incident,
        User $staff,
        string $resolutionCode,
    ): Incident {
        $this->assertStaff($staff);

        return DB::transaction(function () use ($incident, $staff, $resolutionCode): Incident {
            $incident = Incident::query()->lockForUpdate()->findOrFail($incident->id);
            $before = $incident->attributesToArray();
            $incident->forceFill([
                'assigned_to' => $staff->id,
                'status' => IncidentStatus::Resolved,
                'resolution_code' => $resolutionCode,
                'resolved_at' => now(),
            ])->save();
            $this->audit(
                $staff,
                'INCIDENT_RESOLVED',
                $incident,
                $before,
                $resolutionCode,
            );
            $this->notifications->create(
                $incident->reporter,
                'INCIDENT_RESOLVED',
                [
                    'incident_id' => $incident->public_id,
                    'resolution_code' => $resolutionCode,
                ],
                $incident->service_request_id,
            );

            return $incident->load(['reporter', 'assignee', 'serviceRequest']);
        });
    }

    public function moderateRating(
        Rating $rating,
        User $staff,
        RatingModerationStatus $status,
        string $reasonCode,
    ): Rating {
        $this->assertStaff($staff);
        $before = $rating->attributesToArray();
        $rating->forceFill(['moderation_status' => $status])->save();
        $this->audit($staff, 'RATING_MODERATED', $rating, $before, $reasonCode);

        return $rating;
    }

    public function suspendUser(
        User $subject,
        User $admin,
        string $reasonCode,
    ): User {
        abort_unless($admin->hasRole(RoleKey::Admin), 403);

        return DB::transaction(function () use ($subject, $admin, $reasonCode): User {
            $subject = User::query()->lockForUpdate()->findOrFail($subject->id);
            $before = ['status' => $subject->status->value];
            $subject->forceFill(['status' => UserStatus::Suspended])->save();
            $subject->tokens()->delete();
            $subject->driverProfile?->forceFill([
                'availability_status' => DriverAvailabilityStatus::Offline,
                'offline_at' => now(),
            ])->save();
            $this->audit(
                $admin,
                'USER_SUSPENDED',
                $subject,
                $before,
                $reasonCode,
            );

            return $subject;
        });
    }

    private function assertStaff(User $user): void
    {
        abort_unless(
            $user->hasRole(RoleKey::Admin) || $user->hasRole(RoleKey::Support),
            403,
        );
    }

    private function assertAssignedOrAdmin(User $staff, ?int $assignedTo): void
    {
        abort_unless(
            $staff->hasRole(RoleKey::Admin) || $assignedTo === $staff->id,
            403,
        );
    }

    /** @param array<string, mixed> $before */
    private function audit(
        User $actor,
        string $action,
        Model $subject,
        array $before,
        ?string $reasonCode = null,
    ): void {
        AuditLog::query()->create([
            'actor_user_id' => $actor->id,
            'actor_role' => $actor->hasRole(RoleKey::Admin)
                ? RoleKey::Admin->value
                : RoleKey::Support->value,
            'action' => $action,
            'subject_type' => $subject::class,
            'subject_id' => (int) $subject->getKey(),
            'before' => $before,
            'after' => $subject->attributesToArray(),
            'reason_code' => $reasonCode,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'correlation_id' => (string) Str::uuid(),
            'created_at' => now(),
        ]);
    }

    /** @param array<string, mixed> $payload */
    private function outbox(
        string $eventType,
        int $aggregateId,
        ?int $version,
        array $payload,
    ): void {
        OutboxEvent::query()->create([
            'event_type' => $eventType,
            'aggregate_type' => 'SUPPORT',
            'aggregate_id' => $aggregateId,
            'aggregate_version' => $version,
            'payload' => $payload,
            'status' => 'PENDING',
            'attempt_count' => 0,
            'available_at' => now(),
        ]);
    }
}
