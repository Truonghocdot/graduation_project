<?php

namespace App\Filament\Resources\Users\Tables;

use App\Enums\UserStatus;
use App\Models\User;
use App\Services\Support\SupportAdminService;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('public_id')->label('User ID')->copyable(),
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('phone')->searchable(),
                TextColumn::make('roles.name')->badge(),
                TextColumn::make('status')->badge(),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(collect(UserStatus::cases())->mapWithKeys(
                    fn (UserStatus $status): array => [$status->value => $status->value],
                )->all()),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('suspend')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->form([
                        TextInput::make('reason_code')->required()->maxLength(50),
                    ])
                    ->visible(fn (User $record): bool => $record->status === UserStatus::Active)
                    ->action(function (
                        User $record,
                        array $data,
                        SupportAdminService $service,
                    ): void {
                        $admin = auth()->user();
                        abort_unless($admin instanceof User, 403);
                        $service->suspendUser($record, $admin, $data['reason_code']);
                        Notification::make()->title('User suspended')->success()->send();
                    }),
            ])
            ->toolbarActions([]);
    }
}
