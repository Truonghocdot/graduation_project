<?php

namespace App\Filament\Resources\VehicleTypes\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class VehicleTypeInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('unique_key')->label('Unique key')->badge(),
                TextEntry::make('name'),
                TextEntry::make('passenger_capacity'),
                TextEntry::make('max_weight_kg')->suffix(' kg'),
                TextEntry::make('max_length_cm')->suffix(' cm'),
                TextEntry::make('max_width_cm')->suffix(' cm'),
                TextEntry::make('max_height_cm')->suffix(' cm'),
                IconEntry::make('is_active')->boolean(),
                TextEntry::make('created_at')->dateTime(),
            ]);
    }
}
