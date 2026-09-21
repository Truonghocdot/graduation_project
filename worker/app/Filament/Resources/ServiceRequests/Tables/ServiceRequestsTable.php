<?php

namespace App\Filament\Resources\ServiceRequests\Tables;

use App\Enums\PayerType;
use App\Enums\PaymentMethod;
use App\Enums\ServiceRequestStatus;
use App\Enums\ServiceType;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Services\Matching\MatchingAdminService;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
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
            ->recordActions([
                ViewAction::make(),
                Action::make('restartMatching')
                    ->label('Restart matching')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->form([
                        TextInput::make('reason_code')->required()->maxLength(50),
                    ])
                    ->visible(fn (ServiceRequest $record): bool => in_array($record->status, [
                        ServiceRequestStatus::Assigned,
                        ServiceRequestStatus::SearchingDriver,
                        ServiceRequestStatus::DriverArriving,
                        ServiceRequestStatus::DriverArrivingPickup,
                    ], true))
                    ->action(function (ServiceRequest $record, array $data, MatchingAdminService $service): void {
                        $service->restart($record, self::admin(), $data['reason_code']);
                        Notification::make()->title('Matching restarted')->success()->send();
                    }),
                Action::make('cancel')
                    ->label('Cancel')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->form([
                        TextInput::make('reason_code')->required()->maxLength(50),
                    ])
                    ->visible(fn (ServiceRequest $record): bool => in_array($record->status, [
                        ServiceRequestStatus::Scheduled,
                        ServiceRequestStatus::SearchingDriver,
                        ServiceRequestStatus::Assigned,
                        ServiceRequestStatus::DriverArriving,
                        ServiceRequestStatus::DriverArrivingPickup,
                        ServiceRequestStatus::DriverArrived,
                        ServiceRequestStatus::AtPickup,
                    ], true))
                    ->action(function (ServiceRequest $record, array $data, MatchingAdminService $service): void {
                        $service->cancel($record, self::admin(), $data['reason_code']);
                        Notification::make()->title('Service request cancelled')->success()->send();
                    }),
            ])
            ->toolbarActions([]);
    }

    private static function admin(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
