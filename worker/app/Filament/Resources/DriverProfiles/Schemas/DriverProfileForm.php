<?php

namespace App\Filament\Resources\DriverProfiles\Schemas;

use App\Enums\DriverAvailabilityStatus;
use App\Enums\DriverReviewStatus;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class DriverProfileForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('public_id')
                    ->label('Public ID')
                    ->disabled(),
                TextInput::make('user.name')
                    ->label('User')
                    ->disabled(),
                Select::make('review_status')
                    ->options(collect(DriverReviewStatus::cases())->mapWithKeys(
                        fn (DriverReviewStatus $status): array => [$status->value => $status->value],
                    )->all())
                    ->disabled(),
                Select::make('availability_status')
                    ->options(collect(DriverAvailabilityStatus::cases())->mapWithKeys(
                        fn (DriverAvailabilityStatus $status): array => [$status->value => $status->value],
                    )->all())
                    ->disabled(),
                TextInput::make('cod_limit')
                    ->numeric()
                    ->disabled(),
            ]);
    }
}
