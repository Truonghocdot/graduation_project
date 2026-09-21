<?php

namespace App\Filament\Resources\WithdrawalRequests\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class WithdrawalRequestInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('public_id')->copyable(),
            TextEntry::make('wallet.user.name')->label('Driver'),
            TextEntry::make('wallet.user.phone')->label('Phone'),
            TextEntry::make('bankAccount.bank_code')->label('Bank'),
            TextEntry::make('bankAccount.account_name')->label('Account name'),
            TextEntry::make('amount')->money('VND'),
            TextEntry::make('status')->badge(),
            TextEntry::make('requested_at')->dateTime(),
            TextEntry::make('handled_at')->dateTime(),
            TextEntry::make('bank_transfer_reference'),
            TextEntry::make('reason_code'),
        ])->columns(2);
    }
}
