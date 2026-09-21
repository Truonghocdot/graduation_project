<?php

namespace App\Filament\Resources\PricingRules\Tables;

use App\Enums\ServiceType;
use App\Models\PricingRule;
use App\Services\Pricing\PricingService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PricingRulesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('effective_from', 'desc')
            ->columns([
                TextColumn::make('service_type')->badge()->sortable(),
                TextColumn::make('vehicleType.name')->label('Vehicle')->searchable()->sortable(),
                TextColumn::make('base_distance_km')->label('Base km')->numeric(decimalPlaces: 2),
                TextColumn::make('base_fare')->money('VND')->sortable(),
                TextColumn::make('price_per_extra_km')->label('Extra/km')->money('VND')->sortable(),
                TextColumn::make('driver_rate')->numeric(decimalPlaces: 2),
                TextColumn::make('effective_from')->dateTime()->sortable(),
                TextColumn::make('effective_to')->dateTime()->placeholder('Open'),
                IconColumn::make('is_active')->boolean(),
                TextColumn::make('quotes_count')->label('Quotes')->sortable(),
            ])
            ->filters([
                SelectFilter::make('service_type')
                    ->options(collect(ServiceType::cases())->mapWithKeys(
                        fn (ServiceType $serviceType): array => [$serviceType->value => $serviceType->value],
                    )->all()),
                SelectFilter::make('vehicle_type_id')->relationship('vehicleType', 'name'),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                Action::make('preview')
                    ->label('Preview')
                    ->icon('heroicon-o-calculator')
                    ->form([
                        TextInput::make('distance_km')->numeric()->minValue(0)->required(),
                        TextInput::make('voucher_discount')->numeric()->minValue(0)->default(0),
                    ])
                    ->action(function (PricingRule $record, array $data, PricingService $pricingService): void {
                        $result = $pricingService->calculate(
                            $record,
                            (float) $data['distance_km'] * 1_000,
                            (float) ($data['voucher_discount'] ?? 0),
                        );

                        Notification::make()
                            ->title(number_format($result->customerPayable, 0, ',', '.').' VND')
                            ->body('Gross '.number_format($result->grossFare, 0, ',', '.').' VND')
                            ->success()
                            ->send();
                    }),
            ])
            ->toolbarActions([]);
    }
}
