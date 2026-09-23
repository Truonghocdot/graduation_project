<?php

namespace App\Filament\Resources\DriverProfiles\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CapabilitiesRelationManager extends RelationManager
{
    protected static string $relationship = 'capabilities';

    protected static ?string $title = 'Năng lực dịch vụ';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('service_type')
            ->columns([
                TextColumn::make('service_type')->label('Dịch vụ')->badge(),
                TextColumn::make('vehicleType.name')->label('Loại xe'),
                IconColumn::make('is_active')->label('Đang hoạt động')->boolean(),
                TextColumn::make('approved_at')->dateTime()->placeholder('Chưa phê duyệt'),
            ])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
