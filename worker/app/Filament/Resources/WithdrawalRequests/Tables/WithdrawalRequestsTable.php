<?php

namespace App\Filament\Resources\WithdrawalRequests\Tables;

use App\Models\User;
use App\Models\WithdrawalRequest;
use App\Services\Finance\WithdrawalService;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class WithdrawalRequestsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('requested_at', 'desc')
            ->columns([
                TextColumn::make('public_id')->label('Mã yêu cầu rút')->copyable(),
                TextColumn::make('wallet.user.name')->label('Tài xế')->searchable(),
                TextColumn::make('bankAccount.bank_code')->label('Ngân hàng'),
                TextColumn::make('bankAccount.account_name')->label('Tên chủ tài khoản'),
                TextColumn::make('amount')->money('VND')->sortable(),
                TextColumn::make('status')->badge(),
                TextColumn::make('requested_at')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options([
                    'PENDING' => 'Chờ xử lý',
                    'COMPLETED' => 'Đã hoàn tất',
                    'REJECTED' => 'Đã từ chối',
                    'FAILED' => 'Thất bại',
                ]),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('complete')
                    ->color('success')
                    ->form([
                        TextInput::make('bank_transfer_reference')->required()->maxLength(191),
                    ])
                    ->visible(fn (WithdrawalRequest $record): bool => in_array(
                        $record->status,
                        ['PENDING', 'APPROVED'],
                        true,
                    ))
                    ->action(function (
                        WithdrawalRequest $record,
                        array $data,
                        WithdrawalService $service,
                    ): void {
                        $service->complete(
                            $record,
                            self::admin(),
                            $data['bank_transfer_reference'],
                        );
                        Notification::make()->title('Đã hoàn tất yêu cầu rút tiền')->success()->send();
                    }),
                Action::make('reject')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->form([
                        TextInput::make('reason_code')->required()->maxLength(50),
                    ])
                    ->visible(fn (WithdrawalRequest $record): bool => in_array(
                        $record->status,
                        ['PENDING', 'APPROVED'],
                        true,
                    ))
                    ->action(function (
                        WithdrawalRequest $record,
                        array $data,
                        WithdrawalService $service,
                    ): void {
                        $service->reject($record, self::admin(), $data['reason_code']);
                        Notification::make()->title('Đã từ chối yêu cầu rút tiền')->success()->send();
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
