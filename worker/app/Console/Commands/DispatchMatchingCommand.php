<?php

namespace App\Console\Commands;

use App\Enums\ServiceRequestStatus;
use App\Models\ServiceRequest;
use App\Services\Matching\DriverMatchingService;
use Illuminate\Console\Command;

class DispatchMatchingCommand extends Command
{
    protected $signature = 'matching:dispatch {--request= : Public request ID}';

    protected $description = 'Dispatch the next matching batch for searchable service requests';

    public function handle(): int
    {
        $matching = app(DriverMatchingService::class);
        $activated = $matching->activateDueScheduled();
        $query = ServiceRequest::query()
            ->where('status', ServiceRequestStatus::SearchingDriver->value);

        if ($this->option('request') !== null) {
            $query->where('public_id', $this->option('request'));
        }

        $count = 0;
        $query->orderBy('id')->chunkById(100, function ($requests) use ($matching, &$count): void {
            foreach ($requests as $request) {
                $count += $matching->dispatch($request)->count();
            }
        });

        $this->info("Activated {$activated} scheduled requests and created {$count} driver offers.");

        return self::SUCCESS;
    }
}
