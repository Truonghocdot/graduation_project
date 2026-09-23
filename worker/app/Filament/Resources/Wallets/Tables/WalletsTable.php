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
                TextColumn::make('public_id')->label('Mã ví')->copyable(),
                TextColumn::make('user.name')->label('Chủ ví')->searchable(),
                TextColumn::make('user.phone')->label('Số điện thoại')->searchable(),
                TextColumn::make('balance')->money('VND')->sortable(),
                TextColumn::make('reserved_withdrawal_amount')->label('Đã giữ để rút')->money('VND'),
                TextColumn::make('status')->badge(),
                TextColumn::make('updated_at')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options([
                    'ACTIVE' => 'Đang hoạt động',
                    'FROZEN' => 'Đã khóa',
                    'CLOSED' => 'Đã đóng',
                ]),
            ])
            ->recordActions([ViewAction::make()])
            ->toolbarActions([]);
    }
}
