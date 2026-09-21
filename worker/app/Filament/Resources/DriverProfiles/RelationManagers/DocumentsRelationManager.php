<?php

namespace App\Filament\Resources\DriverProfiles\RelationManagers;

use App\Models\DriverDocument;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DocumentsRelationManager extends RelationManager
{
    protected static string $relationship = 'documents';

    protected static ?string $title = 'Documents';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('document_number')
            ->columns([
                TextColumn::make('document_type')->label('Type')->badge(),
                TextColumn::make('document_number')->label('Number')->placeholder('N/A'),
                TextColumn::make('vehicle.plate_number')->label('Vehicle')->placeholder('Personal'),
                TextColumn::make('status')->badge(),
                TextColumn::make('expires_at')->date()->placeholder('No expiry'),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->recordActions([
                Action::make('download')
                    ->label('Download')
                    ->url(fn (DriverDocument $record): string => route(
                        'api.v1.driver-documents.file',
                        $record,
                    ))
                    ->openUrlInNewTab(),
            ])
            ->toolbarActions([]);
    }
}
