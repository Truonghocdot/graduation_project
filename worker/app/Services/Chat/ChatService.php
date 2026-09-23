<?php

namespace App\Services\Chat;

use App\Enums\AssignmentStatus;
use App\Models\Assignment;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\OutboxEvent;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Services\Notification\NotificationService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ChatService
{
    public function __construct(private readonly NotificationService $notifications) {}

    /** @return Collection<int, ChatMessage> */
    public function messages(User $user, ServiceRequest $serviceRequest): Collection
    {
        $conversation = $this->conversation($user, $serviceRequest);
        $conversation->messages()
            ->where('sender_user_id', '<>', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return $conversation->messages()
            ->with('sender')
            ->orderBy('id')
            ->limit(200)
            ->get();
    }

    public function send(
        User $user,
        ServiceRequest $serviceRequest,
        string $clientMessageId,
        string $body,
    ): ChatMessage {
        return DB::transaction(function () use (
            $user,
            $serviceRequest,
            $clientMessageId,
            $body,
        ): ChatMessage {
            $conversation = $this->conversation($user, $serviceRequest);
            $message = ChatMessage::query()->firstOrCreate(
                [
                    'chat_conversation_id' => $conversation->id,
                    'client_message_id' => $clientMessageId,
                ],
                [
                    'sender_user_id' => $user->id,
                    'message_type' => 'TEXT',
                    'body' => $body,
                    'sent_at' => now(),
                    'created_at' => now(),
                ],
            );

            if (! $message->wasRecentlyCreated) {
                if ($message->sender_user_id !== $user->id || $message->body !== $body) {
                    throw ValidationException::withMessages([
                        'client_message_id' => ['Mã tin nhắn phía ứng dụng đã được dùng lại với nội dung khác.'],
                    ]);
                }

                return $message->load('sender');
            }

            $recipientId = $conversation->customer_user_id === $user->id
                ? $conversation->driver_user_id
                : $conversation->customer_user_id;
            $recipient = User::query()->findOrFail($recipientId);
            OutboxEvent::query()->create([
                'event_type' => 'CHAT_MESSAGE_CREATED',
                'aggregate_type' => 'SERVICE_REQUEST',
                'aggregate_id' => $serviceRequest->id,
                'aggregate_version' => $serviceRequest->version,
                'payload' => [
                    'service_request_id' => $serviceRequest->public_id,
                    'conversation_id' => $conversation->public_id,
                    'message_id' => $message->public_id,
                    'sender_user_id' => $user->public_id,
                ],
                'status' => 'PENDING',
                'attempt_count' => 0,
                'available_at' => now(),
            ]);
            $this->notifications->create(
                $recipient,
                'CHAT_MESSAGE_RECEIVED',
                [
                    'service_request_id' => $serviceRequest->public_id,
                    'conversation_id' => $conversation->public_id,
                    'message_id' => $message->public_id,
                ],
                $serviceRequest->id,
                $serviceRequest->version,
            );

            return $message->load('sender');
        });
    }

    public function unreadCount(User $user): int
    {
        return ChatMessage::query()
            ->whereHas('conversation', function ($query) use ($user): void {
                $query->where('customer_user_id', $user->id)
                    ->orWhere('driver_user_id', $user->id);
            })
            ->where('sender_user_id', '<>', $user->id)
            ->whereNull('read_at')
            ->count();
    }

    private function conversation(
        User $user,
        ServiceRequest $serviceRequest,
    ): ChatConversation {
        $assignment = Assignment::query()
            ->where('service_request_id', $serviceRequest->id)
            ->where('status', AssignmentStatus::Active->value)
            ->with('driverProfile')
            ->first();

        if ($assignment === null) {
            throw ValidationException::withMessages([
                'chat' => ['Chỉ có thể trò chuyện khi chuyến được phân công đang hoạt động.'],
            ]);
        }

        $driverUserId = $assignment->driverProfile->user_id;
        if ($user->id !== $serviceRequest->created_by && $user->id !== $driverUserId) {
            abort(404);
        }

        return ChatConversation::query()->firstOrCreate(
            ['assignment_id' => $assignment->id],
            [
                'service_request_id' => $serviceRequest->id,
                'customer_user_id' => $serviceRequest->created_by,
                'driver_user_id' => $driverUserId,
                'status' => 'ACTIVE',
            ],
        );
    }
}
