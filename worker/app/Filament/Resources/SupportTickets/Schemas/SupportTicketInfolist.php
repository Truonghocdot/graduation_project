<?php

namespace App\Filament\Resources\SupportTickets\Schemas;

use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SupportTicketInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Ticket')->schema([
                TextEntry::make('public_id')->copyable(),
                TextEntry::make('priority')->badge(),
                TextEntry::make('category')->badge(),
                TextEntry::make('status')->badge(),
                TextEntry::make('subject'),
                TextEntry::make('opener.name')->label('Opened by'),
                TextEntry::make('assignee.name')->label('Assigned to')->placeholder('Queue'),
                TextEntry::make('description')->columnSpanFull(),
                TextEntry::make('resolution_code')->placeholder('Not resolved'),
                TextEntry::make('resolution_note')->placeholder('Not resolved')->columnSpanFull(),
            ])->columns(2),
            Section::make('Messages')->schema([
                RepeatableEntry::make('messages')->schema([
                    TextEntry::make('sender.name'),
                    TextEntry::make('message_type')->badge(),
                    TextEntry::make('body'),
                    TextEntry::make('created_at')->dateTime(),
                ])->columns(4),
            ]),
            Section::make('Related finance snapshot')->schema([
                TextEntry::make('serviceRequest.public_id')->label('Request ID')->copyable(),
                TextEntry::make('serviceRequest.status')->badge(),
                TextEntry::make('serviceRequest.payment.status')->label('Payment')->badge(),
                TextEntry::make('serviceRequest.payment.customer_payable')->money('VND'),
                TextEntry::make('serviceRequest.payment.settlement.driver_net_earning')
                    ->label('Driver net')
                    ->money('VND')
                    ->placeholder('Not settled'),
            ])->columns(2),
        ]);
    }
}
