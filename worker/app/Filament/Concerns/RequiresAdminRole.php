<?php

namespace App\Filament\Concerns;

use App\Enums\RoleKey;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

trait RequiresAdminRole
{
    public static function canViewAny(): bool
    {
        return self::adminUser();
    }

    public static function canView(Model $record): bool
    {
        return self::adminUser();
    }

    private static function adminUser(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->hasRole(RoleKey::Admin);
    }
}
