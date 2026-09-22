<?php

namespace App\Services\Support;

use App\Enums\RoleKey;
use App\Enums\SupportPriority;
use App\Enums\SupportTicketStatus;
use App\Models\OutboxEvent;
use App\Models\ServiceRequest;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Models\TicketAttachment;
use App\Models\User;
use App\Services\Booking\IdempotencyService;
use App\Services\Notification\NotificationService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class SupportTicketService
{
    public function __construct(
        private readonly NotificationService $notifications,
        private readonly IdempotencyService $idempotency,
    ) {}

    /** @param array<string, mixed> $data */
    public function create(
        User $user,
        array $data,
        ?UploadedFile $attachment = null,
        string $idempotencyKey = '',
    ): SupportTicket {
        return DB::transaction(function () use (
            $user,
            $data,
            $attachment,
            $idempotencyKey,
        ): SupportTicket {
            $idempotency = $this->idempotency->begin(
                $user,
                'support-ticket.create',
                $idempotencyKey,
                $data,
            );

            if ($idempotency->status === 'COMPLETED') {
                $replayed = SupportTicket::query()->findOrFail($idempotency->resource_id);
                $replayed->wasRecentlyCreated = true;

                return $this->load($replayed);
            }

            $serviceRequest = $this->optionalParticipantRequest(
                $user,
                $data['service_request_id'] ?? null,
            );
            $priority = in_array($data['category'], ['SAFETY', 'LOST_ITEM'], true)
                ? SupportPriority::High
                : SupportPriority::Normal;
            $ticket = SupportTicket::query()->create([
                'opened_by' => $user->id,
                'service_request_id' => $serviceRequest?->id,
                'category' => $data['category'],
                'priority' => $priority,
                'status' => SupportTicketStatus::Open,
                'subject' => $data['subject'],
                'description' => $data['description'],
                'version' => 1,
            ]);
            SupportTicketMessage::query()->create([
                'support_ticket_id' => $ticket->id,
                'sender_user_id' => $user->id,
                'message_type' => 'TEXT',
                'body' => $data['description'],
                'created_at' => now(),
            ]);

            if ($attachment !== null) {
                $this->storeAttachment($ticket, $user, $attachment);
            }

            $this->outbox($ticket, 'SUPPORT_TICKET_CREATED');
            $this->notifications->create(
                $user,
                'SUPPORT_TICKET_CREATED',
                [
                    'ticket_id' => $ticket->public_id,
                    'status' => $ticket->status->value,
                ],
                $ticket->id,
                $ticket->version,
            );
            $this->idempotency->complete(
                $idempotency,
                201,
                ['id' => $ticket->public_id],
                SupportTicket::class,
                $ticket->id,
            );

            return $this->load($ticket);
        });
    }

    public function message(
        User $user,
        SupportTicket $ticket,
        string $body,
        ?UploadedFile $attachment = null,
    ): SupportTicket {
        return DB::transaction(function () use ($user, $ticket, $body, $attachment): SupportTicket {
            $ticket = SupportTicket::query()->lockForUpdate()->findOrFail($ticket->id);
            $this->authorize($user, $ticket);

            if ($ticket->status === SupportTicketStatus::Closed) {
                throw ValidationException::withMessages([
                    'ticket' => ['A closed ticket cannot receive new messages.'],
                ]);
            }

            SupportTicketMessage::query()->create([
                'support_ticket_id' => $ticket->id,
                'sender_user_id' => $user->id,
                'message_type' => 'TEXT',
                'body' => $body,
                'created_at' => now(),
            ]);

            if ($attachment !== null) {
                $this->storeAttachment($ticket, $user, $attachment);
            }

            if ($user->id === $ticket->opened_by
                && $ticket->status === SupportTicketStatus::WaitingForCustomer) {
                $ticket->forceFill([
                    'status' => SupportTicketStatus::InReview,
                    'version' => $ticket->version + 1,
                ])->save();
            }

            $this->outbox($ticket, 'SUPPORT_TICKET_UPDATED');
            $recipient = $user->id === $ticket->opened_by
                ? $ticket->assignee
                : $ticket->opener;
            if ($recipient !== null) {
                $this->notifications->create(
                    $recipient,
                    'SUPPORT_TICKET_MESSAGE',
                    ['ticket_id' => $ticket->public_id],
                    $ticket->id,
                    $ticket->version,
                );
            }

            return $this->load($ticket);
        });
    }

    public function authorize(User $user, SupportTicket $ticket): void
    {
        $staff = $user->hasRole(RoleKey::Admin)
            || ($user->hasRole(RoleKey::Support) && $ticket->assigned_to === $user->id);
        abort_unless($ticket->opened_by === $user->id || $staff, 404);
    }

    private function optionalParticipantRequest(User $user, mixed $publicId): ?ServiceRequest
    {
        if (! is_string($publicId) || $publicId === '') {
            return null;
        }

        $request = ServiceRequest::query()->where('public_id', $publicId)->first();

        if ($request === null || ! $this->isParticipant($user, $request)) {
            abort(404);
        }

        return $request;
    }

    private function isParticipant(User $user, ServiceRequest $request): bool
    {
        return $request->created_by === $user->id
            || $request->assignments()
                ->whereHas('driverProfile', fn ($query) => $query->where('user_id', $user->id))
                ->exists();
    }

    private function storeAttachment(
        SupportTicket $ticket,
        User $user,
        UploadedFile $file,
    ): TicketAttachment {
        $path = $file->store('support-tickets/'.$ticket->public_id, 'local');
        if ($path === false) {
            throw new \RuntimeException('The support attachment could not be stored.');
        }

        try {
            return TicketAttachment::query()->create([
                'support_ticket_id' => $ticket->id,
                'uploaded_by' => $user->id,
                'storage_path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                'size_bytes' => $file->getSize(),
                'sha256' => hash_file('sha256', $file->getRealPath()),
                'created_at' => now(),
            ]);
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }
    }

    private function outbox(SupportTicket $ticket, string $eventType): void
    {
        OutboxEvent::query()->create([
            'event_type' => $eventType,
            'aggregate_type' => 'SUPPORT_TICKET',
            'aggregate_id' => $ticket->id,
            'aggregate_version' => $ticket->version,
            'payload' => [
                'ticket_id' => $ticket->public_id,
                'status' => $ticket->status->value,
                'priority' => $ticket->priority->value,
            ],
            'status' => 'PENDING',
            'attempt_count' => 0,
            'available_at' => now(),
        ]);
    }

    private function load(SupportTicket $ticket): SupportTicket
    {
        return $ticket->load([
            'opener',
            'assignee',
            'serviceRequest',
            'messages.sender',
            'attachments.uploader',
        ]);
    }
}
