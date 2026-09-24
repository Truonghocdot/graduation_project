<?php

namespace App\Filament\Resources\PricingRules\Schemas;

use App\Enums\ServiceType;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PricingRuleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Áp dụng cho')
                ->description('Chọn dịch vụ và loại xe. Hai trường này không đổi sau khi đã tạo phiên bản giá.')
                ->schema([
                    Select::make('service_type')
                        ->label('Dịch vụ')
                        ->options(collect(ServiceType::cases())->mapWithKeys(
                            fn (ServiceType $serviceType): array => [$serviceType->value => $serviceType->getLabel()],
                        )->all())
                        ->required()
                        ->disabledOn('edit'),
                    Select::make('vehicle_type_id')
                        ->label('Loại xe')
                        ->relationship('vehicleType', 'name', fn ($query) => $query->where('is_active', true))
                        ->searchable()
                        ->preload()
                        ->required()
                        ->disabledOn('edit'),
                ])
                ->columns(2),
            Section::make('Công thức tính tiền')
                ->description('Nhập tiền theo VND. Ví dụ: 18.000 VND cho phí cơ bản và 5.000 VND cho mỗi km vượt.')
                ->schema([
                    TextInput::make('base_distance_km')
                        ->label('Quãng đường cơ bản')
                        ->numeric()
                        ->minValue(0)
                        ->default(3)
                        ->suffix('km')
                        ->required(),
                    TextInput::make('base_fare')
                        ->label('Phí cơ bản')
                        ->numeric()
                        ->minValue(0)
                        ->suffix('VND')
                        ->required(),
                    TextInput::make('price_per_extra_km')
                        ->label('Phí mỗi km thêm')
                        ->numeric()
                        ->minValue(0)
                        ->suffix('VND/km')
                        ->required(),
                    TextInput::make('driver_rate')
                        ->label('Tỷ lệ tài xế nhận')
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(1)
                        ->step(0.01)
                        ->default(0.88)
                        ->helperText('Nhập dạng thập phân: 0,88 tương đương 88%.')
                        ->required(),
                    TextInput::make('currency')
                        ->label('Đơn vị tiền tệ')
                        ->default('VND')
                        ->maxLength(3)
                        ->required(),
                    DateTimePicker::make('effective_from')
                        ->label('Có hiệu lực từ')
                        ->default(now())
                        ->required()
                        ->disabledOn('edit'),
                    Toggle::make('is_active')
                        ->label('Đang áp dụng')
                        ->default(true),
                ])
                ->columns(2),
        ]);
    }
}
