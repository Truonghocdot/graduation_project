<?php

namespace App\Filament\Resources\Wallets;

use App\Filament\Concerns\RequiresAdminRole;
use App\Filament\Resources\Wallets\Pages\ListWallets;
use App\Filament\Resources\Wallets\Pages\ViewWallet;
use App\Filament\Resources\Wallets\Schemas\WalletInfolist;
use App\Filament\Resources\Wallets\Tables\WalletsTable;
use App\Models\Wallet;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class WalletResource extends Resource
{
    use RequiresAdminRole;

    protected static ?string $model = Wallet::class;

    protected static string|UnitEnum|null $navigationGroup = 'Finance';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWallet;

    public static function infolist(Schema $schema): Schema
    {
        return WalletInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WalletsTable::configure($table);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWallets::route('/'),
            'view' => ViewWallet::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with([
            'user',
            'ledgerAccount.entries' => fn ($query) => $query->with('transaction')->latest('id')->limit(100),
        ]);
    }
}
