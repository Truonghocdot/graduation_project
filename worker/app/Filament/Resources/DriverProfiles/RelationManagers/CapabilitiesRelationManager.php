<?php

namespace App\Filament\Resources\DriverProfiles\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CapabilitiesRelationManager extends RelationManager
{
    protected static string $relationship = 'capabilities';

    protected static ?string $title = 'Service capabilities';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('service_type')
            ->columns([
                TextColumn::make('service_type')->label('Service')->badge(),
                TextColumn::make('vehicleType.name')->label('Vehicle type'),
                IconColumn::make('is_active')->label('Active')->boolean(),
                TextColumn::make('approved_at')->dateTime()->placeholder('Not approved'),
            ])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
