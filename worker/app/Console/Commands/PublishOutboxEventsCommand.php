<?php

namespace App\Console\Commands;

use App\Services\Matching\OutboxEventPublisher;
use Illuminate\Console\Command;

class PublishOutboxEventsCommand extends Command
{
    protected $signature = 'outbox:publish {--limit=100}';

    protected $description = 'Publish pending outbox events to the realtime event channel';

    public function handle(): int
    {
        $published = app(OutboxEventPublisher::class)->publish((int) $this->option('limit'));
        $this->info("Published {$published} outbox events.");

        return self::SUCCESS;
    }
}
