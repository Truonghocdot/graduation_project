<?php

namespace App\Filament\Resources\Payments\Tables;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\User;
use App\Services\Finance\RefundService;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PaymentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('public_id')->label('Payment ID')->copyable(),
                TextColumn::make('serviceRequest.public_id')->label('Request')->copyable(),
                TextColumn::make('payer.name')->label('Payer')->searchable(),
                TextColumn::make('method')->badge(),
                TextColumn::make('status')->badge(),
                TextColumn::make('gross_fare')->money('VND'),
                TextColumn::make('voucher_discount')->money('VND'),
                TextColumn::make('customer_payable')->money('VND'),
                TextColumn::make('updated_at')->dateTime(),
            ])
            ->filters([
                SelectFilter::make('method')->options(['WALLET' => 'WALLET', 'CASH' => 'CASH']),
                SelectFilter::make('status')->options(collect(PaymentStatus::cases())->mapWithKeys(
                    fn (PaymentStatus $status): array => [$status->value => $status->value],
                )->all()),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('refund')
                    ->color('warning')
                    ->form([
                        TextInput::make('amount')->numeric()->minValue(1)->required(),
                        TextInput::make('reason_code')->required()->maxLength(50),
                        Textarea::make('evidence')
                            ->label('Cash evidence JSON')
                            ->json()
                            ->rows(4),
                    ])
                    ->visible(fn (Payment $record): bool => in_array($record->status, [
                        PaymentStatus::Settled,
                        PaymentStatus::PartiallyRefunded,
                    ], true))
                    ->action(function (
                        Payment $record,
                        array $data,
                        RefundService $service,
                    ): void {
                        $evidence = filled($data['evidence'] ?? null)
                            ? json_decode($data['evidence'], true, flags: JSON_THROW_ON_ERROR)
                            : null;
                        $service->refund(
                            $record,
                            self::admin(),
                            (float) $data['amount'],
                            $data['reason_code'],
                            $evidence,
                        );
                        Notification::make()->title('Refund completed')->success()->send();
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
