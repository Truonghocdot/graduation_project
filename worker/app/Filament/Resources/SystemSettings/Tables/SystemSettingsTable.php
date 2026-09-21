<?php

namespace App\Filament\Resources\SystemSettings\Tables;

use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SystemSettingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('key')
            ->columns([
                TextColumn::make('key')->badge()->searchable()->sortable(),
                TextColumn::make('value')
                    ->formatStateUsing(fn (mixed $state): string => json_encode($state) ?: 'null')
                    ->limit(50),
                IconColumn::make('is_public')->boolean(),
                TextColumn::make('updatedBy.name')->label('Updated by'),
                TextColumn::make('updated_at')->dateTime()->sortable(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([]);
    }
}
