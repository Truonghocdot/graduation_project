<?php

namespace App\Filament\Resources\DriverProfiles\Schemas;

use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class DriverProfileInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('public_id')->label('ID')->copyable(),
                TextEntry::make('user.name')->label('User'),
                TextEntry::make('user.phone')->label('Phone'),
                TextEntry::make('review_status')->badge(),
                TextEntry::make('availability_status')->badge(),
                TextEntry::make('review_reason_code')->label('Review reason')->placeholder('None'),
                TextEntry::make('cod_limit')->numeric(decimalPlaces: 0)->suffix(' VND'),
                TextEntry::make('submitted_at')->dateTime(),
                TextEntry::make('reviewed_at')->dateTime(),
                RepeatableEntry::make('vehicles')
                    ->label('Vehicles')
                    ->schema([
                        TextEntry::make('vehicleType.name')->label('Type'),
                        TextEntry::make('plate_number')->label('Plate'),
                        TextEntry::make('status')->badge(),
                        TextEntry::make('is_selected')->label('Selected'),
                    ])
                    ->columns(4),
                RepeatableEntry::make('documents')
                    ->label('Documents')
                    ->schema([
                        TextEntry::make('document_type')->label('Type')->badge(),
                        TextEntry::make('document_number')->label('Number'),
                        TextEntry::make('status')->badge(),
                        TextEntry::make('expires_at')->date(),
                    ])
                    ->columns(4),
            ]);
    }
}
