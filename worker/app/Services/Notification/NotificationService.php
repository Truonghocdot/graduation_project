<?php

namespace App\Services\Notification;

use App\Models\OutboxEvent;
use App\Models\User;
use App\Models\UserNotification;

class NotificationService
{
    /** @param array<string, mixed> $data */
    public function create(
        User $user,
        string $type,
        array $data,
        ?int $aggregateId = null,
        ?int $aggregateVersion = null,
    ): UserNotification {
        $notification = UserNotification::query()->create([
            'user_id' => $user->id,
            'type' => $type,
            'channel' => 'IN_APP',
            'data' => $data,
            'status' => 'SENT',
            'sent_at' => now(),
        ]);
        OutboxEvent::query()->create([
            'event_type' => 'NOTIFICATION_CREATED',
            'aggregate_type' => 'NOTIFICATION',
            'aggregate_id' => $aggregateId ?? 0,
            'aggregate_version' => $aggregateVersion,
            'payload' => [
                'notification_id' => $notification->id,
                'user_id' => $user->public_id,
                'type' => $type,
            ],
            'status' => 'PENDING',
            'attempt_count' => 0,
            'available_at' => now(),
        ]);

        return $notification;
    }
}
