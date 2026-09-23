<?php

namespace App\Filament\Resources\WithdrawalRequests;

use App\Filament\Concerns\RequiresAdminRole;
use App\Filament\Resources\WithdrawalRequests\Pages\ListWithdrawalRequests;
use App\Filament\Resources\WithdrawalRequests\Pages\ViewWithdrawalRequest;
use App\Filament\Resources\WithdrawalRequests\Schemas\WithdrawalRequestInfolist;
use App\Filament\Resources\WithdrawalRequests\Tables\WithdrawalRequestsTable;
use App\Models\WithdrawalRequest;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class WithdrawalRequestResource extends Resource
{
    use RequiresAdminRole;

    protected static ?string $model = WithdrawalRequest::class;

    protected static string|UnitEnum|null $navigationGroup = 'Tài chính';

    protected static ?string $navigationLabel = 'Yêu cầu rút tiền';

    protected static ?string $modelLabel = 'yêu cầu rút tiền';

    protected static ?string $pluralModelLabel = 'yêu cầu rút tiền';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUpTray;

    public static function infolist(Schema $schema): Schema
    {
        return WithdrawalRequestInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WithdrawalRequestsTable::configure($table);
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
            'index' => ListWithdrawalRequests::route('/'),
            'view' => ViewWithdrawalRequest::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with([
            'wallet.user',
            'bankAccount.driverProfile.user',
        ]);
    }
}
