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
                TextColumn::make('public_id')->label('Mã yêu cầu')->copyable()->searchable(),
                TextColumn::make('service_type')->badge()->sortable(),
                TextColumn::make('status')->badge()->sortable(),
                TextColumn::make('creator.name')->label('Khách hàng')->searchable(),
                TextColumn::make('vehicleType.name')->label('Loại xe')->sortable(),
                TextColumn::make('deliveryOrder.payer_type')->label('Người thanh toán')->badge()->placeholder('Người đặt'),
                TextColumn::make('payment.method')->label('Phương thức thanh toán')->badge(),
                TextColumn::make('payment.customer_payable')->label('Số tiền phải trả')->money('VND')->sortable(),
                TextColumn::make('scheduled_at')->dateTime()->placeholder('Ngay bây giờ')->sortable(),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('service_type')->options(collect(ServiceType::cases())->mapWithKeys(
                    fn (ServiceType $type): array => [$type->value => $type->getLabel()],
                )->all()),
                SelectFilter::make('status')->options(collect(ServiceRequestStatus::cases())->mapWithKeys(
                    fn (ServiceRequestStatus $status): array => [$status->value => $status->getLabel()],
                )->all()),
                SelectFilter::make('payment_method')
                    ->options(collect(PaymentMethod::cases())->mapWithKeys(
                        fn (PaymentMethod $method): array => [$method->value => $method->getLabel()],
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
                        fn (PayerType $payer): array => [$payer->value => $payer->getLabel()],
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
                    ->label('Tìm lại tài xế')
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
                        Notification::make()->title('Đã bắt đầu tìm lại tài xế')->success()->send();
                    }),
                Action::make('cancel')
                    ->label('Hủy')
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
                        Notification::make()->title('Đã hủy yêu cầu dịch vụ')->success()->send();
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
