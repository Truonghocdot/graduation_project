<?php

namespace App\Filament\Resources\Settlements;

use App\Filament\Concerns\RequiresAdminRole;
use App\Filament\Resources\Settlements\Pages\ListSettlements;
use App\Filament\Resources\Settlements\Pages\ViewSettlement;
use App\Filament\Resources\Settlements\Schemas\SettlementInfolist;
use App\Filament\Resources\Settlements\Tables\SettlementsTable;
use App\Models\Settlement;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class SettlementResource extends Resource
{
    use RequiresAdminRole;

    protected static ?string $model = Settlement::class;

    protected static string|UnitEnum|null $navigationGroup = 'Tài chính';

    protected static ?string $modelLabel = 'quyết toán';

    protected static ?string $pluralModelLabel = 'quyết toán';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    public static function infolist(Schema $schema): Schema
    {
        return SettlementInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SettlementsTable::configure($table);
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
            'index' => ListSettlements::route('/'),
            'view' => ViewSettlement::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with([
            'payment.serviceRequest',
            'assignment.vehicle',
            'driverProfile.user',
        ]);
    }
}
