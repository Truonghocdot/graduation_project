<?php

namespace App\Filament\Resources\DriverProfiles;

use App\Filament\Resources\DriverProfiles\Pages\ListDriverProfiles;
use App\Filament\Resources\DriverProfiles\Pages\ViewDriverProfile;
use App\Filament\Resources\DriverProfiles\RelationManagers\CapabilitiesRelationManager;
use App\Filament\Resources\DriverProfiles\RelationManagers\DocumentsRelationManager;
use App\Filament\Resources\DriverProfiles\RelationManagers\VehiclesRelationManager;
use App\Filament\Resources\DriverProfiles\Schemas\DriverProfileForm;
use App\Filament\Resources\DriverProfiles\Schemas\DriverProfileInfolist;
use App\Filament\Resources\DriverProfiles\Tables\DriverProfilesTable;
use App\Models\DriverProfile;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class DriverProfileResource extends Resource
{
    protected static ?string $model = DriverProfile::class;

    protected static string|UnitEnum|null $navigationGroup = 'Driver Operations';

    protected static ?string $navigationLabel = 'Driver applications';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return DriverProfileForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return DriverProfileInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DriverProfilesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            DocumentsRelationManager::class,
            VehiclesRelationManager::class,
            CapabilitiesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDriverProfiles::route('/'),
            'view' => ViewDriverProfile::route('/{record}'),
        ];
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

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with([
                'user',
                'documents.vehicle',
                'vehicles.vehicleType',
                'capabilities.vehicleType',
                'lastLocation',
            ])
            ->withCount(['documents', 'vehicles']);
    }
}
