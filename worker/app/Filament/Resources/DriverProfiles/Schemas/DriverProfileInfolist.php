<?php

namespace App\Filament\Resources\DriverProfiles\Schemas;

use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class DriverProfileInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('public_id')->label('Mã')->copyable(),
                TextEntry::make('user.name')->label('Người dùng'),
                TextEntry::make('user.phone')->label('Số điện thoại'),
                TextEntry::make('review_status')->badge(),
                TextEntry::make('availability_status')->badge(),
                TextEntry::make('review_reason_code')->label('Lý do xét duyệt')->placeholder('Không có'),
                TextEntry::make('cod_limit')
                    ->label('Hạn mức ứng COD mỗi ngày')
                    ->numeric(decimalPlaces: 0)
                    ->suffix(' VND'),
                TextEntry::make('submitted_at')->dateTime(),
                TextEntry::make('reviewed_at')->dateTime(),
                RepeatableEntry::make('vehicles')
                    ->label('Xe')
                    ->schema([
                        TextEntry::make('vehicleType.name')->label('Loại xe'),
                        TextEntry::make('plate_number')->label('Biển số'),
                        TextEntry::make('status')->badge(),
                        TextEntry::make('is_selected')->label('Được chọn'),
                    ])
                    ->columns(4),
                RepeatableEntry::make('documents')
                    ->label('Giấy tờ')
                    ->schema([
                        TextEntry::make('document_type')->label('Loại giấy tờ')->badge(),
                        TextEntry::make('document_number')->label('Số giấy tờ'),
                        TextEntry::make('status')->badge(),
                        TextEntry::make('expires_at')->date(),
                    ])
                    ->columns(4),
            ]);
    }
}
