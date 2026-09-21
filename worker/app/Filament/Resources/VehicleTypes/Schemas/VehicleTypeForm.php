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
                    ->label('Unique key')
                    ->required()
                    ->maxLength(50)
                    ->unique(ignoreRecord: true)
                    ->disabledOn('edit'),
                TextInput::make('name')
                    ->required()
                    ->maxLength(100),
                TextInput::make('passenger_capacity')
                    ->label('Passenger capacity')
                    ->numeric()
                    ->minValue(1),
                TextInput::make('max_weight_kg')
                    ->label('Max weight (kg)')
                    ->numeric()
                    ->minValue(0),
                TextInput::make('max_length_cm')
                    ->label('Max length (cm)')
                    ->numeric()
                    ->minValue(0),
                TextInput::make('max_width_cm')
                    ->label('Max width (cm)')
                    ->numeric()
                    ->minValue(0),
                TextInput::make('max_height_cm')
                    ->label('Max height (cm)')
                    ->numeric()
                    ->minValue(0),
                Toggle::make('is_active')
                    ->label('Active')
                    ->default(true),
            ]);
    }
}
