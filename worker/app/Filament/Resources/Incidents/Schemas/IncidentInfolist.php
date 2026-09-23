<?php

namespace App\Filament\Resources\Incidents\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class IncidentInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('public_id')->copyable(),
            TextEntry::make('severity')->badge(),
            TextEntry::make('incident_type')->badge(),
            TextEntry::make('status')->badge(),
            TextEntry::make('reporter.name')->label('Người báo cáo'),
            TextEntry::make('assignee.name')->label('Người phụ trách')->placeholder('Hàng chờ'),
            TextEntry::make('serviceRequest.public_id')->label('Mã yêu cầu')->copyable(),
            TextEntry::make('serviceRequest.status')->label('Trạng thái yêu cầu')->badge(),
            TextEntry::make('description')->columnSpanFull(),
            TextEntry::make('evidence')
                ->formatStateUsing(fn (mixed $state): string => json_encode(
                    $state,
                    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES,
                ) ?: '{}')
                ->columnSpanFull(),
            TextEntry::make('resolution_code')->placeholder('Chưa xử lý'),
            TextEntry::make('resolved_at')->dateTime(),
        ])->columns(2);
    }
}
