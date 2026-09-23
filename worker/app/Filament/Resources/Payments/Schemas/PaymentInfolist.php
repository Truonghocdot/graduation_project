<?php

namespace App\Filament\Resources\Payments\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class PaymentInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('public_id')->copyable(),
            TextEntry::make('serviceRequest.public_id')->label('Mã yêu cầu')->copyable(),
            TextEntry::make('payer.name'),
            TextEntry::make('payer_type')->badge(),
            TextEntry::make('method')->badge(),
            TextEntry::make('status')->badge(),
            TextEntry::make('gross_fare')->money('VND'),
            TextEntry::make('voucher_discount')->money('VND'),
            TextEntry::make('customer_payable')->money('VND'),
            TextEntry::make('cash_collected')->money('VND'),
            TextEntry::make('settlement.public_id')->label('Mã quyết toán')->copyable(),
        ])->columns(2);
    }
}
