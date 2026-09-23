<?php

namespace App\Filament\Resources\ServiceAreas\Schemas;

use App\Enums\ServiceType;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ServiceAreaForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required()->maxLength(120),
            Select::make('service_type')
                ->options(collect(ServiceType::cases())->mapWithKeys(
                    fn (ServiceType $serviceType): array => [$serviceType->value => $serviceType->getLabel()],
                )->all())
                ->nullable(),
            Toggle::make('is_active')->default(true),
            Textarea::make('boundary')
                ->formatStateUsing(fn (mixed $state): string => is_string($state)
                    ? $state
                    : (json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}'))
                ->json()
                ->rows(16)
                ->required()
                ->columnSpanFull(),
        ])->columns(2);
    }
}
