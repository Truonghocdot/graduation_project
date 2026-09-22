<?php

namespace App\Filament\Resources\VehicleTypes;

use App\Filament\Concerns\RequiresAdminRole;
use App\Filament\Resources\VehicleTypes\Pages\CreateVehicleType;
use App\Filament\Resources\VehicleTypes\Pages\EditVehicleType;
use App\Filament\Resources\VehicleTypes\Pages\ListVehicleTypes;
use App\Filament\Resources\VehicleTypes\Pages\ViewVehicleType;
use App\Filament\Resources\VehicleTypes\Schemas\VehicleTypeForm;
use App\Filament\Resources\VehicleTypes\Schemas\VehicleTypeInfolist;
use App\Filament\Resources\VehicleTypes\Tables\VehicleTypesTable;
use App\Models\VehicleType;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class VehicleTypeResource extends Resource
{
    use RequiresAdminRole;

    protected static ?string $model = VehicleType::class;

    protected static string|UnitEnum|null $navigationGroup = 'Catalog';

    protected static ?string $navigationLabel = 'Vehicle types';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return VehicleTypeForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return VehicleTypeInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return VehicleTypesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVehicleTypes::route('/'),
            'create' => CreateVehicleType::route('/create'),
            'view' => ViewVehicleType::route('/{record}'),
            'edit' => EditVehicleType::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
