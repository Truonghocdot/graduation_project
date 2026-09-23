<?php

namespace App\Filament\Resources\Ratings\Tables;

use App\Enums\RatingModerationStatus;
use App\Models\Rating;
use App\Models\User;
use App\Services\Support\SupportAdminService;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class RatingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('serviceRequest.public_id')->label('Yêu cầu')->copyable(),
                TextColumn::make('direction')->badge(),
                TextColumn::make('reviewer.name')->label('Người đánh giá')->searchable(),
                TextColumn::make('reviewee.name')->label('Người được đánh giá')->searchable(),
                TextColumn::make('score')->sortable(),
                TextColumn::make('comment')->limit(50),
                TextColumn::make('moderation_status')->badge(),
                TextColumn::make('created_at')->dateTime(),
            ])
            ->filters([
                SelectFilter::make('moderation_status')->options(collect(RatingModerationStatus::cases())->mapWithKeys(
                    fn (RatingModerationStatus $status): array => [$status->value => $status->getLabel()],
                )->all()),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('moderate')
                    ->form([
                        Select::make('status')
                            ->options(collect(RatingModerationStatus::cases())->mapWithKeys(
                                fn (RatingModerationStatus $status): array => [$status->value => $status->getLabel()],
                            )->all())
                            ->required(),
                        TextInput::make('reason_code')->required()->maxLength(50),
                    ])
                    ->action(function (
                        Rating $record,
                        array $data,
                        SupportAdminService $service,
                    ): void {
                        $service->moderateRating(
                            $record,
                            self::staff(),
                            RatingModerationStatus::from($data['status']),
                            $data['reason_code'],
                        );
                        Notification::make()->title('Đã kiểm duyệt đánh giá')->success()->send();
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
