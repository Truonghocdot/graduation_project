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

    protected static ?string $title = 'Giấy tờ';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('document_number')
            ->columns([
                TextColumn::make('document_type')->label('Loại giấy tờ')->badge(),
                TextColumn::make('document_number')->label('Số giấy tờ')->placeholder('Không có'),
                TextColumn::make('vehicle.plate_number')->label('Xe')->placeholder('Cá nhân'),
                TextColumn::make('status')->badge(),
                TextColumn::make('expires_at')->date()->placeholder('Không hết hạn'),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->recordActions([
                Action::make('download')
                    ->label('Tải xuống')
                    ->url(fn (DriverDocument $record): string => route(
                        'api.v1.driver-documents.file',
                        $record,
                    ))
                    ->openUrlInNewTab(),
            ])
            ->toolbarActions([]);
    }
}
