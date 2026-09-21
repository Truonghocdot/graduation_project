<?php

namespace App\Filament\Resources\DriverProfiles\RelationManagers;

use App\Models\DriverBankAccount;
use App\Models\User;
use App\Services\Finance\BankAccountReviewService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class BankAccountsRelationManager extends RelationManager
{
    protected static string $relationship = 'bankAccounts';

    protected static ?string $title = 'Bank accounts';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('bank_code')->badge(),
                TextColumn::make('account_name'),
                IconColumn::make('is_verified')->boolean(),
                IconColumn::make('is_default')->boolean(),
                TextColumn::make('created_at')->dateTime(),
            ])
            ->recordActions([
                Action::make('verify')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (DriverBankAccount $record): bool => ! $record->is_verified)
                    ->action(function (
                        DriverBankAccount $record,
                        BankAccountReviewService $service,
                    ): void {
                        $user = auth()->user();
                        abort_unless($user instanceof User, 403);
                        $service->verify($record, $user);
                        Notification::make()->title('Bank account verified')->success()->send();
                    }),
            ])
            ->toolbarActions([]);
    }
}
