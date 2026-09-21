<?php

namespace App\Filament\Resources\ServiceAreas\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class ServiceAreaInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('name'),
            TextEntry::make('service_type')->badge()->placeholder('ALL'),
            IconEntry::make('is_active')->boolean(),
            TextEntry::make('updated_at')->dateTime(),
            TextEntry::make('boundary')
                ->formatStateUsing(fn (mixed $state): string => json_encode(
                    $state,
                    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES,
                ) ?: '{}')
                ->columnSpanFull(),
        ])->columns(2);
    }
}
