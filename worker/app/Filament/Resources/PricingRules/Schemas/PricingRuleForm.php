<?php

namespace App\Filament\Resources\PricingRules\Schemas;

use App\Enums\ServiceType;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class PricingRuleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('service_type')
                ->options(collect(ServiceType::cases())->mapWithKeys(
                    fn (ServiceType $serviceType): array => [$serviceType->value => $serviceType->value],
                )->all())
                ->required()
                ->disabledOn('edit'),
            Select::make('vehicle_type_id')
                ->relationship('vehicleType', 'name', fn ($query) => $query->where('is_active', true))
                ->searchable()
                ->preload()
                ->required()
                ->disabledOn('edit'),
            TextInput::make('base_distance_km')
                ->numeric()
                ->minValue(0)
                ->default(3)
                ->required(),
            TextInput::make('base_fare')
                ->numeric()
                ->minValue(0)
                ->suffix('VND')
                ->required(),
            TextInput::make('price_per_extra_km')
                ->numeric()
                ->minValue(0)
                ->suffix('VND/km')
                ->required(),
            TextInput::make('driver_rate')
                ->numeric()
                ->minValue(0)
                ->maxValue(1)
                ->step(0.01)
                ->default(0.88)
                ->required(),
            TextInput::make('currency')
                ->default('VND')
                ->maxLength(3)
                ->required(),
            DateTimePicker::make('effective_from')
                ->default(now())
                ->required()
                ->disabledOn('edit'),
            Toggle::make('is_active')
                ->default(true),
        ])->columns(2);
    }
}
