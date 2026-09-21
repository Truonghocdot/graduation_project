<?php

namespace App\Console\Commands;

use App\Services\Matching\OfferExpiryService;
use Illuminate\Console\Command;

class ExpireDriverOffersCommand extends Command
{
    protected $signature = 'matching:expire-offers';

    protected $description = 'Expire pending driver offers';

    public function handle(): int
    {
        $expired = app(OfferExpiryService::class)->expire();
        $this->info("Expired {$expired} driver offers.");

        return self::SUCCESS;
    }
}
