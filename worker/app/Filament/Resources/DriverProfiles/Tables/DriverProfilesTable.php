<?php

namespace App\Filament\Resources\DriverProfiles\Tables;

use App\Enums\DriverAvailabilityStatus;
use App\Enums\DriverReviewStatus;
use App\Models\DriverProfile;
use App\Models\User;
use App\Services\Driver\DriverReviewService;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class DriverProfilesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')
                    ->label('Tài xế')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('user.phone')
                    ->label('Số điện thoại')
                    ->searchable(),
                TextColumn::make('review_status')
                    ->badge()
                    ->sortable(),
                TextColumn::make('availability_status')
                    ->badge()
                    ->sortable(),
                TextColumn::make('vehicles_count')
                    ->counts('vehicles')
                    ->label('Xe'),
                TextColumn::make('documents_count')
                    ->counts('documents')
                    ->label('Giấy tờ'),
                TextColumn::make('submitted_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('review_status')->options(collect(DriverReviewStatus::cases())->mapWithKeys(
                    fn (DriverReviewStatus $status): array => [$status->value => $status->getLabel()],
                )->all()),
                SelectFilter::make('availability_status')->options(collect(DriverAvailabilityStatus::cases())->mapWithKeys(
                    fn (DriverAvailabilityStatus $status): array => [$status->value => $status->getLabel()],
                )->all()),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('approve')
                    ->label('Phê duyệt')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (DriverProfile $record): bool => $record->review_status === DriverReviewStatus::PendingReview)
                    ->action(function (DriverProfile $record, DriverReviewService $review): void {
                        $review->approve($record, self::admin());
                        Notification::make()->title('Đã phê duyệt tài xế')->success()->send();
                    }),
                Action::make('reject')
                    ->label('Từ chối')
                    ->color('danger')
                    ->form([
                        TextInput::make('reason_code')->required()->maxLength(50),
                    ])
                    ->visible(fn (DriverProfile $record): bool => $record->review_status === DriverReviewStatus::PendingReview)
                    ->action(function (DriverProfile $record, array $data, DriverReviewService $review): void {
                        $review->reject($record, self::admin(), $data['reason_code']);
                        Notification::make()->title('Đã từ chối hồ sơ tài xế')->success()->send();
                    }),
                Action::make('suspend')
                    ->label('Tạm ngưng')
                    ->color('danger')
                    ->form([
                        TextInput::make('reason_code')->required()->maxLength(50),
                    ])
                    ->visible(fn (DriverProfile $record): bool => $record->review_status === DriverReviewStatus::Approved)
                    ->action(function (DriverProfile $record, array $data, DriverReviewService $review): void {
                        $review->suspend($record, self::admin(), $data['reason_code']);
                        Notification::make()->title('Đã tạm ngưng tài xế')->success()->send();
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
