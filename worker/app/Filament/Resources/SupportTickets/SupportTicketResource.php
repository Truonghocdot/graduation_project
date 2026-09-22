<?php

namespace App\Filament\Resources\SupportTickets;

use App\Enums\RoleKey;
use App\Filament\Resources\SupportTickets\Pages\ListSupportTickets;
use App\Filament\Resources\SupportTickets\Pages\ViewSupportTicket;
use App\Filament\Resources\SupportTickets\Schemas\SupportTicketInfolist;
use App\Filament\Resources\SupportTickets\Tables\SupportTicketsTable;
use App\Models\SupportTicket;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class SupportTicketResource extends Resource
{
    protected static ?string $model = SupportTicket::class;

    protected static string|UnitEnum|null $navigationGroup = 'Support';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTicket;

    public static function infolist(Schema $schema): Schema
    {
        return SupportTicketInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SupportTicketsTable::configure($table);
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
            'index' => ListSupportTickets::route('/'),
            'view' => ViewSupportTicket::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with([
            'opener',
            'assignee',
            'serviceRequest.payment.settlement',
            'messages.sender',
            'attachments.uploader',
        ]);
        $user = auth()->user();

        if ($user instanceof User && ! $user->hasRole(RoleKey::Admin)) {
            $query->where(function (Builder $query) use ($user): void {
                $query->whereNull('assigned_to')->orWhere('assigned_to', $user->id);
            });
        }

        return $query;
    }
}
