<?php

namespace App\Filament\Resources\Wallets\Schemas;

use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class WalletInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Ví')->schema([
                TextEntry::make('public_id')->label('Mã ví')->copyable(),
                TextEntry::make('user.name')->label('Chủ ví'),
                TextEntry::make('user.phone')->label('Số điện thoại'),
                TextEntry::make('balance')->money('VND'),
                TextEntry::make('reserved_withdrawal_amount')->money('VND'),
                TextEntry::make('status')->badge(),
            ])->columns(2),
            Section::make('Bút toán sổ cái')->schema([
                RepeatableEntry::make('ledgerAccount.entries')
                    ->schema([
                        TextEntry::make('transaction.transaction_type')->label('Loại'),
                        TextEntry::make('direction')->badge(),
                        TextEntry::make('amount')->money('VND'),
                        TextEntry::make('balance_after')->money('VND'),
                        TextEntry::make('created_at')->dateTime(),
                    ])->columns(5),
            ]),
        ]);
    }
}
