<?php

namespace App\Filament\Resources\SystemSettings\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;

class SystemSettingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make('Cấu hình')
                ->tabs([
                    Tab::make('Giá cước')->components([
                        Select::make('key')
                            ->options([
                                'pricing.quote_ttl_seconds' => 'Thời hạn báo giá (giây)',
                                'pricing.rounding_unit' => 'Đơn vị làm tròn VND',
                                'pricing.float_tolerance' => 'Sai số số thực',
                            ])
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->disabledOn('edit'),
                        Textarea::make('value')
                            ->formatStateUsing(fn (mixed $state): string => is_string($state)
                                ? $state
                                : (json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: 'null'))
                            ->json()
                            ->required()
                            ->rows(6),
                        Toggle::make('is_public')->default(false),
                    ])->columns(2),
                ])
                ->columnSpanFull(),
        ]);
    }
}
