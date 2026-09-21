<?php

namespace App\Filament\Resources\Settlements\Pages;

use App\Filament\Resources\Settlements\SettlementResource;
use Filament\Resources\Pages\ViewRecord;

class ViewSettlement extends ViewRecord
{
    protected static string $resource = SettlementResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
