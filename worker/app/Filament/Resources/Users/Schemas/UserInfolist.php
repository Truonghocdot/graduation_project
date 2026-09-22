<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class UserInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('public_id')->copyable(),
            TextEntry::make('name'),
            TextEntry::make('phone'),
            TextEntry::make('email'),
            TextEntry::make('status')->badge(),
            TextEntry::make('roles.name')->badge(),
            TextEntry::make('phone_verified_at')->dateTime(),
            TextEntry::make('last_login_at')->dateTime(),
            TextEntry::make('created_at')->dateTime(),
        ])->columns(2);
    }
}
