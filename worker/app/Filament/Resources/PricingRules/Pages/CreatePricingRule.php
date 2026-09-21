<?php

namespace App\Filament\Resources\PricingRules\Pages;

use App\Filament\Resources\PricingRules\PricingRuleResource;
use App\Models\User;
use App\Services\Admin\PricingCatalogAdminService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreatePricingRule extends CreateRecord
{
    protected static string $resource = PricingRuleResource::class;

    /** @param array<string, mixed> $data */
    protected function handleRecordCreation(array $data): Model
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        return app(PricingCatalogAdminService::class)->createPricingRule($data, $user);
    }
}
