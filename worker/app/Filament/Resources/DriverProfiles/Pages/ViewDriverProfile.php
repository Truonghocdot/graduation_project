<?php

namespace App\Filament\Resources\DriverProfiles\Pages;

use App\Enums\DriverReviewStatus;
use App\Filament\Resources\DriverProfiles\DriverProfileResource;
use App\Models\DriverProfile;
use App\Models\User;
use App\Services\Driver\DriverReviewService;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewDriverProfile extends ViewRecord
{
    protected static string $resource = DriverProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('approve')
                ->label('Approve')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn (): bool => $this->driver()->review_status === DriverReviewStatus::PendingReview)
                ->action(function (DriverReviewService $review): void {
                    $review->approve($this->driver(), $this->admin());
                    $this->refreshFormData(['review_status', 'availability_status', 'reviewed_at']);
                    Notification::make()->title('Driver approved')->success()->send();
                }),
            Action::make('reject')
                ->label('Reject')
                ->color('danger')
                ->form([
                    TextInput::make('reason_code')->required()->maxLength(50),
                ])
                ->visible(fn (): bool => $this->driver()->review_status === DriverReviewStatus::PendingReview)
                ->action(function (array $data, DriverReviewService $review): void {
                    $review->reject($this->driver(), $this->admin(), $data['reason_code']);
                    $this->refreshFormData(['review_status', 'review_reason_code', 'reviewed_at']);
                    Notification::make()->title('Driver application rejected')->success()->send();
                }),
            Action::make('suspend')
                ->label('Suspend')
                ->color('danger')
                ->form([
                    TextInput::make('reason_code')->required()->maxLength(50),
                ])
                ->visible(fn (): bool => $this->driver()->review_status === DriverReviewStatus::Approved)
                ->action(function (array $data, DriverReviewService $review): void {
                    $review->suspend($this->driver(), $this->admin(), $data['reason_code']);
                    $this->refreshFormData(['review_status', 'availability_status', 'review_reason_code']);
                    Notification::make()->title('Driver suspended')->success()->send();
                }),
        ];
    }

    private function driver(): DriverProfile
    {
        /** @var DriverProfile $record */
        $record = $this->getRecord();

        return $record;
    }

    private function admin(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
