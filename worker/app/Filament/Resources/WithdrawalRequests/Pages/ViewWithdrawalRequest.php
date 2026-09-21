<?php

namespace App\Filament\Resources\WithdrawalRequests\Pages;

use App\Filament\Resources\WithdrawalRequests\WithdrawalRequestResource;
use Filament\Resources\Pages\ViewRecord;

class ViewWithdrawalRequest extends ViewRecord
{
    protected static string $resource = WithdrawalRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
