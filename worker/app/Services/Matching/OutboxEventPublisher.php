<?php

namespace App\Services\Matching;

use App\Models\OutboxEvent;
use Illuminate\Support\Facades\Redis;

class OutboxEventPublisher
{
    public function publish(int $limit = 100): int
    {
        $published = 0;
        $events = OutboxEvent::query()
            ->whereIn('status', ['PENDING', 'FAILED'])
            ->where('available_at', '<=', now())
            ->orderBy('id')
            ->limit($limit)
            ->get();

        foreach ($events as $event) {
            try {
                Redis::publish(config('matching.outbox_channel', 'worker.outbox'), json_encode([
                    'event_id' => $event->event_id,
                    'event_type' => $event->event_type,
                    'aggregate_type' => $event->aggregate_type,
                    'aggregate_id' => $event->aggregate_id,
                    'aggregate_version' => $event->aggregate_version,
                    'payload' => $event->payload,
                    'occurred_at' => $event->created_at?->toISOString(),
                ], JSON_THROW_ON_ERROR));
                $event->forceFill([
                    'status' => 'PUBLISHED',
                    'published_at' => now(),
                ])->save();
                $published++;
            } catch (\Throwable $exception) {
                $event->forceFill([
                    'status' => 'FAILED',
                    'attempt_count' => $event->attempt_count + 1,
                    'last_error' => $exception->getMessage(),
                    'available_at' => now()->addSeconds(min(300, 2 ** min(8, $event->attempt_count + 1))),
                ])->save();
            }
        }

        return $published;
    }
}
