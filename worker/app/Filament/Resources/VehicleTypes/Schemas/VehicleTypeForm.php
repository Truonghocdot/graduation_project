<?php

namespace App\Filament\Resources\VehicleTypes\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class VehicleTypeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('unique_key')
                    ->label('Mã duy nhất')
                    ->required()
                    ->maxLength(50)
                    ->unique(ignoreRecord: true)
                    ->disabledOn('edit'),
                TextInput::make('name')
                    ->required()
                    ->maxLength(100),
                TextInput::make('passenger_capacity')
                    ->label('Sức chứa hành khách')
                    ->numeric()
                    ->minValue(1),
                TextInput::make('max_weight_kg')
                    ->label('Khối lượng tối đa (kg)')
                    ->numeric()
                    ->minValue(0),
                TextInput::make('max_length_cm')
                    ->label('Chiều dài tối đa (cm)')
                    ->numeric()
                    ->minValue(0),
                TextInput::make('max_width_cm')
                    ->label('Chiều rộng tối đa (cm)')
                    ->numeric()
                    ->minValue(0),
                TextInput::make('max_height_cm')
                    ->label('Chiều cao tối đa (cm)')
                    ->numeric()
                    ->minValue(0),
                Toggle::make('is_active')
                    ->label('Đang hoạt động')
                    ->default(true),
            ]);
    }
}
