<?php

namespace App\Filament\Resources\Settlements\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SettlementsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('public_id')->label('Settlement ID')->copyable(),
                TextColumn::make('payment.serviceRequest.public_id')->label('Request')->copyable(),
                TextColumn::make('driverProfile.user.name')->label('Driver')->searchable(),
                TextColumn::make('status')->badge(),
                TextColumn::make('driver_gross_earning')->label('Gross')->money('VND'),
                TextColumn::make('platform_fee_debited')->label('Fee')->money('VND'),
                TextColumn::make('driver_net_earning')->label('Net')->money('VND'),
                TextColumn::make('settled_at')->dateTime(),
            ])
            ->filters([
                SelectFilter::make('status')->options([
                    'PENDING' => 'PENDING',
                    'PROCESSING' => 'PROCESSING',
                    'SETTLED' => 'SETTLED',
                    'FAILED' => 'FAILED',
                ]),
            ])
            ->recordActions([ViewAction::make()])
            ->toolbarActions([]);
    }
}
