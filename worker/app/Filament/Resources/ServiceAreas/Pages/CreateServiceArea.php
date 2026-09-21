<?php

namespace App\Filament\Resources\ServiceAreas\Pages;

use App\Filament\Resources\ServiceAreas\ServiceAreaResource;
use App\Models\User;
use App\Services\Admin\PricingCatalogAdminService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateServiceArea extends CreateRecord
{
    protected static string $resource = ServiceAreaResource::class;

    /** @param array<string, mixed> $data */
    protected function handleRecordCreation(array $data): Model
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        return app(PricingCatalogAdminService::class)->createServiceArea($data, $user);
    }
}
