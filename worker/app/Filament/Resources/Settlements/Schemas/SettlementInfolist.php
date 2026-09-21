<?php

namespace App\Filament\Resources\Settlements\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class SettlementInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('public_id')->copyable(),
            TextEntry::make('status')->badge(),
            TextEntry::make('payment.serviceRequest.public_id')->label('Request ID')->copyable(),
            TextEntry::make('driverProfile.user.name')->label('Driver'),
            TextEntry::make('driver_rate')->numeric(decimalPlaces: 2),
            TextEntry::make('driver_gross_earning')->money('VND'),
            TextEntry::make('cash_collected')->money('VND'),
            TextEntry::make('wallet_payment_amount')->money('VND'),
            TextEntry::make('voucher_payment_amount')->money('VND'),
            TextEntry::make('platform_fee_debited')->money('VND'),
            TextEntry::make('settlement_adjustment')->money('VND'),
            TextEntry::make('driver_net_earning')->money('VND'),
            TextEntry::make('settled_at')->dateTime(),
        ])->columns(2);
    }
}
