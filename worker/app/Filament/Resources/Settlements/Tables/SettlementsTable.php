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
                TextColumn::make('public_id')->label('Mã quyết toán')->copyable(),
                TextColumn::make('payment.serviceRequest.public_id')->label('Yêu cầu')->copyable(),
                TextColumn::make('driverProfile.user.name')->label('Tài xế')->searchable(),
                TextColumn::make('status')->badge(),
                TextColumn::make('driver_gross_earning')->label('Thu nhập gộp')->money('VND'),
                TextColumn::make('platform_fee_debited')->label('Phí nền tảng')->money('VND'),
                TextColumn::make('driver_net_earning')->label('Thu nhập thực nhận')->money('VND'),
                TextColumn::make('settled_at')->dateTime(),
            ])
            ->filters([
                SelectFilter::make('status')->options([
                    'PENDING' => 'Chờ xử lý',
                    'PROCESSING' => 'Đang xử lý',
                    'SETTLED' => 'Đã quyết toán',
                    'FAILED' => 'Thất bại',
                ]),
            ])
            ->recordActions([ViewAction::make()])
            ->toolbarActions([]);
    }
}
