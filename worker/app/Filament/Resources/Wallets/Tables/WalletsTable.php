<?php

namespace App\Filament\Resources\Wallets\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class WalletsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('updated_at', 'desc')
            ->columns([
                TextColumn::make('public_id')->label('Wallet ID')->copyable(),
                TextColumn::make('user.name')->label('Owner')->searchable(),
                TextColumn::make('user.phone')->label('Phone')->searchable(),
                TextColumn::make('balance')->money('VND')->sortable(),
                TextColumn::make('reserved_withdrawal_amount')->label('Reserved')->money('VND'),
                TextColumn::make('status')->badge(),
                TextColumn::make('updated_at')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options([
                    'ACTIVE' => 'ACTIVE',
                    'FROZEN' => 'FROZEN',
                    'CLOSED' => 'CLOSED',
                ]),
            ])
            ->recordActions([ViewAction::make()])
            ->toolbarActions([]);
    }
}
