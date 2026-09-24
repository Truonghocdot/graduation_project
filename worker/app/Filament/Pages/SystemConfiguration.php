<?php

namespace App\Filament\Pages;

use App\Enums\RoleKey;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\Admin\PricingCatalogAdminService;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class SystemConfiguration extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|UnitEnum|null $navigationGroup = 'Giá cước';

    protected static ?string $navigationLabel = 'Cấu hình hệ thống';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static ?int $navigationSort = 30;

    protected string $view = 'filament.pages.system-configuration';

    /** @var array<string, int|float> */
    public array $data = [];

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->hasRole(RoleKey::Admin);
    }

    public function mount(): void
    {
        $this->form->fill([
            'quote_ttl_seconds' => $this->setting('pricing.quote_ttl_seconds', 300),
            'rounding_unit' => $this->setting('pricing.rounding_unit', 1_000),
            'float_tolerance' => $this->setting('pricing.float_tolerance', 0.01),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Giá trị vận hành')
                    ->description('Các thay đổi được áp dụng cho báo giá mới sau khi lưu.')
                    ->schema([
                        TextInput::make('quote_ttl_seconds')
                            ->label('Thời hạn báo giá')
                            ->numeric()
                            ->suffix('giây')
                            ->minValue(60)
                            ->maxValue(3_600)
                            ->required()
                            ->helperText('Từ 60 đến 3.600 giây. Mặc định: 300 giây.'),
                        TextInput::make('rounding_unit')
                            ->label('Đơn vị làm tròn tiền')
                            ->numeric()
                            ->suffix('VND')
                            ->minValue(1)
                            ->maxValue(100_000)
                            ->required()
                            ->helperText('Ví dụ 1.000 sẽ làm tròn giá về bội số 1.000 VND.'),
                        TextInput::make('float_tolerance')
                            ->label('Sai số số thực')
                            ->numeric()
                            ->step(0.01)
                            ->minValue(0)
                            ->maxValue(1)
                            ->required()
                            ->helperText('Khoảng 0 đến 1, dùng khi so sánh các phép tính tiền.'),
                    ])
                    ->columns(3),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        app(PricingCatalogAdminService::class)->saveSystemSettings(
            $this->form->getState(),
            $user,
        );

        Notification::make()
            ->title('Đã lưu cấu hình hệ thống')
            ->success()
            ->send();
    }

    private function setting(string $key, int|float $default): int|float
    {
        $value = SystemSetting::query()->whereKey($key)->value('value');

        return is_numeric($value) ? (float) $value : $default;
    }
}
