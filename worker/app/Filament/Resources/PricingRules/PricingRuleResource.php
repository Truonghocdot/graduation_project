<?php

namespace App\Filament\Resources\PricingRules;

use App\Filament\Resources\PricingRules\Pages\CreatePricingRule;
use App\Filament\Resources\PricingRules\Pages\EditPricingRule;
use App\Filament\Resources\PricingRules\Pages\ListPricingRules;
use App\Filament\Resources\PricingRules\Pages\ViewPricingRule;
use App\Filament\Resources\PricingRules\Schemas\PricingRuleForm;
use App\Filament\Resources\PricingRules\Schemas\PricingRuleInfolist;
use App\Filament\Resources\PricingRules\Tables\PricingRulesTable;
use App\Models\PricingRule;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class PricingRuleResource extends Resource
{
    protected static ?string $model = PricingRule::class;

    protected static string|UnitEnum|null $navigationGroup = 'Pricing';

    protected static ?string $navigationLabel = 'Pricing rules';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    public static function form(Schema $schema): Schema
    {
        return PricingRuleForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return PricingRuleInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PricingRulesTable::configure($table);
    }

    public static function canEdit(Model $record): bool
    {
        return $record instanceof PricingRule && ! $record->quotes()->exists();
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPricingRules::route('/'),
            'create' => CreatePricingRule::route('/create'),
            'view' => ViewPricingRule::route('/{record}'),
            'edit' => EditPricingRule::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['vehicleType', 'creator'])
            ->withCount('quotes');
    }
}
