<?php

namespace App\Filament\Resources\SystemSettings;

use App\Filament\Concerns\RequiresAdminRole;
use App\Filament\Resources\SystemSettings\Pages\CreateSystemSetting;
use App\Filament\Resources\SystemSettings\Pages\EditSystemSetting;
use App\Filament\Resources\SystemSettings\Pages\ListSystemSettings;
use App\Filament\Resources\SystemSettings\Pages\ViewSystemSetting;
use App\Filament\Resources\SystemSettings\Schemas\SystemSettingForm;
use App\Filament\Resources\SystemSettings\Schemas\SystemSettingInfolist;
use App\Filament\Resources\SystemSettings\Tables\SystemSettingsTable;
use App\Models\SystemSetting;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class SystemSettingResource extends Resource
{
    use RequiresAdminRole;

    protected static ?string $model = SystemSetting::class;

    protected static ?string $recordTitleAttribute = 'key';

    protected static string|UnitEnum|null $navigationGroup = 'Giá cước';

    protected static ?string $navigationLabel = 'Thiết lập';

    protected static ?string $modelLabel = 'thiết lập hệ thống';

    protected static ?string $pluralModelLabel = 'thiết lập hệ thống';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    public static function form(Schema $schema): Schema
    {
        return SystemSettingForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return SystemSettingInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SystemSettingsTable::configure($table);
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSystemSettings::route('/'),
            'create' => CreateSystemSetting::route('/create'),
            'view' => ViewSystemSetting::route('/{record}'),
            'edit' => EditSystemSetting::route('/{record}/edit'),
        ];
    }
}
