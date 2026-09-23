<?php

namespace App\Filament\Resources\SupportTickets\Tables;

use App\Enums\SupportPriority;
use App\Enums\SupportTicketStatus;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\Support\SupportAdminService;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SupportTicketsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('public_id')->label('Mã yêu cầu')->copyable(),
                TextColumn::make('priority')->badge()->sortable(),
                TextColumn::make('category')->badge(),
                TextColumn::make('subject')->searchable()->limit(45),
                TextColumn::make('opener.name')->label('Người tạo')->searchable(),
                TextColumn::make('assignee.name')->label('Người phụ trách')->placeholder('Hàng chờ'),
                TextColumn::make('status')->badge()->sortable(),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('priority')->options(collect(SupportPriority::cases())->mapWithKeys(
                    fn (SupportPriority $priority): array => [$priority->value => $priority->getLabel()],
                )->all()),
                SelectFilter::make('status')->options(collect(SupportTicketStatus::cases())->mapWithKeys(
                    fn (SupportTicketStatus $status): array => [$status->value => $status->getLabel()],
                )->all()),
                SelectFilter::make('assigned_to')->relationship('assignee', 'name'),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('claim')
                    ->icon('heroicon-o-hand-raised')
                    ->visible(fn (SupportTicket $record): bool => $record->assigned_to === null)
                    ->action(function (SupportTicket $record, SupportAdminService $service): void {
                        $user = self::staff();
                        $service->assignTicket($record, $user, $user);
                        Notification::make()->title('Đã nhận xử lý yêu cầu')->success()->send();
                    }),
                Action::make('resolve')
                    ->color('success')
                    ->form([
                        TextInput::make('resolution_code')->required()->maxLength(50),
                        Textarea::make('resolution_note')->required()->maxLength(5000),
                    ])
                    ->visible(fn (SupportTicket $record): bool => ! in_array($record->status, [
                        SupportTicketStatus::Resolved,
                        SupportTicketStatus::Closed,
                    ], true))
                    ->action(function (
                        SupportTicket $record,
                        array $data,
                        SupportAdminService $service,
                    ): void {
                        $service->resolveTicket(
                            $record,
                            self::staff(),
                            $data['resolution_code'],
                            $data['resolution_note'],
                        );
                        Notification::make()->title('Đã xử lý yêu cầu hỗ trợ')->success()->send();
                    }),
            ])
            ->toolbarActions([]);
    }

    private static function staff(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
