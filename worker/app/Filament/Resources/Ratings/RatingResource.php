<?php

namespace App\Filament\Resources\Ratings;

use App\Filament\Resources\Ratings\Pages\ListRatings;
use App\Filament\Resources\Ratings\Pages\ViewRating;
use App\Filament\Resources\Ratings\Schemas\RatingInfolist;
use App\Filament\Resources\Ratings\Tables\RatingsTable;
use App\Models\Rating;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class RatingResource extends Resource
{
    protected static ?string $model = Rating::class;

    protected static string|UnitEnum|null $navigationGroup = 'Support';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedStar;

    public static function infolist(Schema $schema): Schema
    {
        return RatingInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RatingsTable::configure($table);
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
            'index' => ListRatings::route('/'),
            'view' => ViewRating::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with([
            'reviewer',
            'reviewee',
            'serviceRequest',
        ]);
    }
}
