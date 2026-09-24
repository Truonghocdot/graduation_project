<?php

namespace App\Filament\Resources\ServiceAreas\Schemas;

use App\Enums\ServiceType;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ViewField;
use Filament\Schemas\Schema;

class ServiceAreaForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label('Tên khu vực')
                ->placeholder('Ví dụ: Thành phố Hồ Chí Minh')
                ->helperText('Tên dễ nhận biết để dùng khi cấu hình giá và kiểm tra báo giá.')
                ->required()
                ->maxLength(120),
            Select::make('service_type')
                ->label('Áp dụng cho dịch vụ')
                ->options(collect(ServiceType::cases())->mapWithKeys(
                    fn (ServiceType $serviceType): array => [$serviceType->value => $serviceType->getLabel()],
                )->all())
                ->helperText('Để trống nếu khu vực áp dụng cho cả giao hàng và đặt xe.')
                ->nullable(),
            Toggle::make('is_active')->label('Đang hoạt động')->default(true),
            ViewField::make('boundary')
                ->label('Ranh giới khu vực')
                ->view('filament.forms.goong-boundary-picker', [
                    'mapKey' => config('services.goong.map_key'),
                ])
                ->required()
                ->columnSpanFull(),
        ])->columns(2);
    }
}
