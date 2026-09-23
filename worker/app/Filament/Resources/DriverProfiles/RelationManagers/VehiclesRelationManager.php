<?php

namespace App\Filament\Resources\DriverProfiles\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class VehiclesRelationManager extends RelationManager
{
    protected static string $relationship = 'vehicles';

    protected static ?string $title = 'Xe';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('plate_number')
            ->columns([
                TextColumn::make('vehicleType.name')->label('Loại xe'),
                TextColumn::make('plate_number')->label('Biển số')->searchable(),
                TextColumn::make('brand'),
                TextColumn::make('model'),
                TextColumn::make('color'),
                TextColumn::make('status')->badge(),
                IconColumn::make('is_selected')->label('Được chọn')->boolean(),
            ])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
