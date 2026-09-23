<?php

namespace App\Filament\Resources\Ratings\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class RatingInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('serviceRequest.public_id')->label('Mã yêu cầu')->copyable(),
            TextEntry::make('direction')->badge(),
            TextEntry::make('reviewer.name'),
            TextEntry::make('reviewee.name'),
            TextEntry::make('score'),
            TextEntry::make('moderation_status')->badge(),
            TextEntry::make('tags')->badge(),
            TextEntry::make('comment')->columnSpanFull(),
            TextEntry::make('created_at')->dateTime(),
        ])->columns(2);
    }
}
