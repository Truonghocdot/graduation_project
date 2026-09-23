<?php

namespace App\Filament\Resources\ServiceAreas;

use App\Filament\Concerns\RequiresAdminRole;
use App\Filament\Resources\ServiceAreas\Pages\CreateServiceArea;
use App\Filament\Resources\ServiceAreas\Pages\EditServiceArea;
use App\Filament\Resources\ServiceAreas\Pages\ListServiceAreas;
use App\Filament\Resources\ServiceAreas\Pages\ViewServiceArea;
use App\Filament\Resources\ServiceAreas\Schemas\ServiceAreaForm;
use App\Filament\Resources\ServiceAreas\Schemas\ServiceAreaInfolist;
use App\Filament\Resources\ServiceAreas\Tables\ServiceAreasTable;
use App\Models\ServiceArea;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class ServiceAreaResource extends Resource
{
    use RequiresAdminRole;

    protected static ?string $model = ServiceArea::class;

    protected static string|UnitEnum|null $navigationGroup = 'Giá cước';

    protected static ?string $navigationLabel = 'Khu vực phục vụ';

    protected static ?string $modelLabel = 'khu vực phục vụ';

    protected static ?string $pluralModelLabel = 'khu vực phục vụ';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMap;

    public static function form(Schema $schema): Schema
    {
        return ServiceAreaForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ServiceAreaInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ServiceAreasTable::configure($table);
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListServiceAreas::route('/'),
            'create' => CreateServiceArea::route('/create'),
            'view' => ViewServiceArea::route('/{record}'),
            'edit' => EditServiceArea::route('/{record}/edit'),
        ];
    }
}
