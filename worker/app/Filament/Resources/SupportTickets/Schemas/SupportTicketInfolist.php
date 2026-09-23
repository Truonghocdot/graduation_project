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
            Section::make('Yêu cầu hỗ trợ')->schema([
                TextEntry::make('public_id')->copyable(),
                TextEntry::make('priority')->badge(),
                TextEntry::make('category')->badge(),
                TextEntry::make('status')->badge(),
                TextEntry::make('subject'),
                TextEntry::make('opener.name')->label('Người tạo'),
                TextEntry::make('assignee.name')->label('Người phụ trách')->placeholder('Hàng chờ'),
                TextEntry::make('description')->columnSpanFull(),
                TextEntry::make('resolution_code')->placeholder('Chưa xử lý'),
                TextEntry::make('resolution_note')->placeholder('Chưa xử lý')->columnSpanFull(),
            ])->columns(2),
            Section::make('Tin nhắn')->schema([
                RepeatableEntry::make('messages')->schema([
                    TextEntry::make('sender.name'),
                    TextEntry::make('message_type')->badge(),
                    TextEntry::make('body'),
                    TextEntry::make('created_at')->dateTime(),
                ])->columns(4),
            ]),
            Section::make('Thông tin tài chính liên quan')->schema([
                TextEntry::make('serviceRequest.public_id')->label('Mã yêu cầu')->copyable(),
                TextEntry::make('serviceRequest.status')->badge(),
                TextEntry::make('serviceRequest.payment.status')->label('Thanh toán')->badge(),
                TextEntry::make('serviceRequest.payment.customer_payable')->money('VND'),
                TextEntry::make('serviceRequest.payment.settlement.driver_net_earning')
                    ->label('Thu nhập thực nhận của tài xế')
                    ->money('VND')
                    ->placeholder('Chưa quyết toán'),
            ])->columns(2),
        ]);
    }
}
