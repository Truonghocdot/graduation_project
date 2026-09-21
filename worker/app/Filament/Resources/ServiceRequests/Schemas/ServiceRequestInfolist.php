<?php

namespace App\Filament\Resources\ServiceRequests\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ServiceRequestInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Request')->schema([
                TextEntry::make('public_id')->label('Request ID')->copyable(),
                TextEntry::make('service_type')->badge(),
                TextEntry::make('status')->badge(),
                TextEntry::make('booking_type')->badge(),
                TextEntry::make('creator.name')->label('Customer'),
                TextEntry::make('creator.phone')->label('Customer phone'),
                TextEntry::make('vehicleType.name')->label('Vehicle type'),
                TextEntry::make('scheduled_at')->dateTime()->placeholder('Now'),
                TextEntry::make('search_started_at')->dateTime()->placeholder('Not started'),
                TextEntry::make('cancellation_reason_code')->placeholder('None'),
            ])->columns(2),
            Section::make('Payment')->schema([
                TextEntry::make('payment.public_id')->label('Payment ID')->copyable(),
                TextEntry::make('payment.payer_type')->badge(),
                TextEntry::make('payment.payer.name')->label('Payer'),
                TextEntry::make('payment.method')->badge(),
                TextEntry::make('payment.status')->badge(),
                TextEntry::make('payment.gross_fare')->money('VND'),
                TextEntry::make('payment.voucher_discount')->money('VND'),
                TextEntry::make('payment.customer_payable')->money('VND'),
            ])->columns(2),
            Section::make('Service detail')->schema([
                TextEntry::make('deliveryOrder.goods_type')->label('Goods')->placeholder('Not delivery'),
                TextEntry::make('deliveryOrder.cod_amount')->label('COD')->money('VND')->placeholder('0'),
                TextEntry::make('rideBooking.passenger_count')->label('Passengers')->placeholder('Not a ride'),
                TextEntry::make('quote.distance_meters')->label('Distance')->suffix(' m'),
                TextEntry::make('quote.duration_seconds')->label('Duration')->suffix(' s'),
            ])->columns(2),
        ]);
    }
}
