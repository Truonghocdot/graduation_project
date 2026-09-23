<?php

namespace App\Filament\Resources\SystemSettings\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class SystemSettingInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('key')->badge(),
            IconEntry::make('is_public')->boolean(),
            TextEntry::make('value')
                ->formatStateUsing(fn (mixed $state): string => json_encode(
                    $state,
                    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES,
                ) ?: 'null'),
            TextEntry::make('updatedBy.name')->label('Người cập nhật')->placeholder('Hệ thống'),
            TextEntry::make('updated_at')->dateTime(),
        ])->columns(2);
    }
}
