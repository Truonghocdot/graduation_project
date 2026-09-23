<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Auth\Login;
use App\Filament\Support\VietnameseLabel;
use Filament\Forms\Components\Field;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Infolists\Components\Entry;
use Filament\Infolists\Components\TextEntry;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Tables\Columns\Column;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\BaseFilter;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function boot(): void
    {
        Field::configureUsing(static function (Field $component): void {
            self::applyVietnameseLabel($component, $component->getName());
        });
        Entry::configureUsing(static function (Entry $component): void {
            self::applyVietnameseLabel($component, $component->getName());
        });
        Column::configureUsing(static function (Column $component): void {
            self::applyVietnameseLabel($component, $component->getName());
        });
        TextEntry::configureUsing(static function (TextEntry $component): void {
            $component->formatStateUsing(
                static fn (mixed $state): mixed => VietnameseLabel::value($component->getName(), $state),
            );
        });
        TextColumn::configureUsing(static function (TextColumn $component): void {
            $component->formatStateUsing(
                static fn (mixed $state): mixed => VietnameseLabel::value($component->getName(), $state),
            );
        });
        BaseFilter::configureUsing(static function (BaseFilter $component): void {
            self::applyVietnameseLabel($component, $component->getName());
        });
    }

    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login(Login::class)
            ->colors([
                'primary' => Color::Amber,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
                FilamentInfoWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }

    private static function applyVietnameseLabel(Field|Entry|Column|BaseFilter $component, string $name): void
    {
        $label = VietnameseLabel::for($name);

        if ($label !== null) {
            $component->label($label);
        }
    }
}
