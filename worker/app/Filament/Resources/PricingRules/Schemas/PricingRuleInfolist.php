<?php

namespace App\Filament\Resources\PricingRules\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class PricingRuleInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('service_type')->badge(),
            TextEntry::make('vehicleType.name')->label('Loại xe'),
            TextEntry::make('base_distance_km')->suffix(' km'),
            TextEntry::make('base_fare')->money('VND'),
            TextEntry::make('price_per_extra_km')->money('VND'),
            TextEntry::make('driver_rate')->numeric(decimalPlaces: 2),
            TextEntry::make('effective_from')->dateTime(),
            TextEntry::make('effective_to')->dateTime()->placeholder('Không giới hạn'),
            IconEntry::make('is_active')->boolean(),
            TextEntry::make('quotes_count')->label('Báo giá'),
            TextEntry::make('creator.name')->label('Người tạo'),
            TextEntry::make('created_at')->dateTime(),
        ])->columns(2);
    }
}
