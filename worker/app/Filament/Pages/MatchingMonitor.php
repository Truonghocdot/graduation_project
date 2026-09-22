<?php

namespace App\Filament\Pages;

use App\Enums\RoleKey;
use App\Enums\ServiceRequestStatus;
use App\Models\ServiceRequest;
use App\Models\User;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use UnitEnum;

class MatchingMonitor extends Page
{
    protected static string|UnitEnum|null $navigationGroup = 'Operations';

    protected static ?string $navigationLabel = 'Matching monitor';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSignal;

    protected string $view = 'filament.pages.matching-monitor';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->hasRole(RoleKey::Admin);
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        /** @var Collection<int, ServiceRequest> $requests */
        $requests = ServiceRequest::query()
            ->whereNotIn('status', [
                ServiceRequestStatus::Completed->value,
                ServiceRequestStatus::Cancelled->value,
            ])
            ->with([
                'creator',
                'vehicleType',
                'driverOffers' => fn ($query) => $query->latest('offered_at'),
                'assignments' => fn ($query) => $query->where('status', 'ACTIVE')
                    ->with('driverProfile.user'),
            ])
            ->orderBy('created_at')
            ->get();

        return ['requests' => $requests];
    }
}
