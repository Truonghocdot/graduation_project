<?php

namespace App\Filament\Resources\ServiceRequests\Tables;

use App\Enums\PayerType;
use App\Enums\PaymentMethod;
use App\Enums\ServiceRequestStatus;
use App\Enums\ServiceType;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ServiceRequestsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('public_id')->label('Request ID')->copyable()->searchable(),
                TextColumn::make('service_type')->badge()->sortable(),
                TextColumn::make('status')->badge()->sortable(),
                TextColumn::make('creator.name')->label('Customer')->searchable(),
                TextColumn::make('vehicleType.name')->label('Vehicle')->sortable(),
                TextColumn::make('deliveryOrder.payer_type')->label('Payer')->badge()->placeholder('ORDERER'),
                TextColumn::make('payment.method')->label('Payment')->badge(),
                TextColumn::make('payment.customer_payable')->label('Payable')->money('VND')->sortable(),
                TextColumn::make('scheduled_at')->dateTime()->placeholder('Now')->sortable(),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('service_type')->options(collect(ServiceType::cases())->mapWithKeys(
                    fn (ServiceType $type): array => [$type->value => $type->value],
                )->all()),
                SelectFilter::make('status')->options(collect(ServiceRequestStatus::cases())->mapWithKeys(
                    fn (ServiceRequestStatus $status): array => [$status->value => $status->value],
                )->all()),
                SelectFilter::make('payment_method')
                    ->options(collect(PaymentMethod::cases())->mapWithKeys(
                        fn (PaymentMethod $method): array => [$method->value => $method->value],
                    )->all())
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        $data['value'] ?? null,
                        fn (Builder $query, string $method): Builder => $query->whereHas(
                            'payment',
                            fn (Builder $query): Builder => $query->where('method', $method),
                        ),
                    )),
                SelectFilter::make('payer_type')
                    ->options(collect(PayerType::cases())->mapWithKeys(
                        fn (PayerType $payer): array => [$payer->value => $payer->value],
                    )->all())
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        $data['value'] ?? null,
                        fn (Builder $query, string $payer): Builder => $query->whereHas(
                            'payment',
                            fn (Builder $query): Builder => $query->where('payer_type', $payer),
                        ),
                    )),
                Filter::make('scheduled')
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('scheduled_at')),
            ])
            ->recordActions([ViewAction::make()])
            ->toolbarActions([]);
    }
}
