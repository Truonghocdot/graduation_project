<?php

namespace App\Filament\Resources\Incidents\Tables;

use App\Enums\IncidentStatus;
use App\Models\Incident;
use App\Models\User;
use App\Services\Support\SupportAdminService;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class IncidentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('public_id')->label('Mã sự cố')->copyable(),
                TextColumn::make('severity')->badge()->sortable(),
                TextColumn::make('incident_type')->badge(),
                TextColumn::make('reporter.name')->label('Người báo cáo')->searchable(),
                TextColumn::make('serviceRequest.public_id')->label('Yêu cầu')->copyable(),
                TextColumn::make('assignee.name')->label('Người phụ trách')->placeholder('Hàng chờ'),
                TextColumn::make('status')->badge(),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(collect(IncidentStatus::cases())->mapWithKeys(
                    fn (IncidentStatus $status): array => [$status->value => $status->getLabel()],
                )->all()),
                SelectFilter::make('severity')->options([
                    'NORMAL' => 'Bình thường',
                    'HIGH' => 'Cao',
                    'CRITICAL' => 'Nghiêm trọng',
                ]),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('resolve')
                    ->color('success')
                    ->form([
                        TextInput::make('resolution_code')->required()->maxLength(50),
                    ])
                    ->visible(fn (Incident $record): bool => $record->status !== IncidentStatus::Resolved)
                    ->action(function (
                        Incident $record,
                        array $data,
                        SupportAdminService $service,
                    ): void {
                        $service->resolveIncident(
                            $record,
                            self::staff(),
                            $data['resolution_code'],
                        );
                        Notification::make()->title('Đã xử lý sự cố')->success()->send();
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
