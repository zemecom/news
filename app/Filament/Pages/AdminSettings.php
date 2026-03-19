<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Services\AdminSettingsService;
use BackedEnum;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use LogicException;
use Override;
use UnitEnum;

final class AdminSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?string $navigationLabel = 'Admin Settings';

    protected static ?string $slug = 'settings';

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 1;

    protected static ?string $title = 'Admin Settings';

    protected string $view = 'filament.pages.admin-settings';

    /**
     * @var array<string, mixed>
     */
    public array $data = [];

    public function mount(AdminSettingsService $settings): void
    {
        $record = $settings->getRecord();

        $this->getFormSchema()->fill([
            'news_auto_refresh_enabled' => $record->news_auto_refresh_enabled,
            'news_auto_refresh_interval_seconds' => $record->news_auto_refresh_interval_seconds,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('News Table')
                    ->description('Дефолты для страницы News. Администратор может временно переопределить интервал прямо в списке новостей.')
                    ->compact()
                    ->columns(2)
                    ->components([
                        Toggle::make('news_auto_refresh_enabled')
                            ->label('Enable auto-refresh by default')
                            ->helperText('Если выключено, список новостей открывается без polling.')
                            ->live(),
                        Select::make('news_auto_refresh_interval_seconds')
                            ->label('Default interval')
                            ->options(app(AdminSettingsService::class)->newsAutoRefreshIntervalSecondsOptions())
                            ->native(false)
                            ->required()
                            ->disabled(fn (Get $get): bool => ! (bool) $get('news_auto_refresh_enabled')),
                        Placeholder::make('news_auto_refresh_summary')
                            ->label('Current behavior')
                            ->content(fn (Get $get): string => $this->newsAutoRefreshSummary(
                                enabled: (bool) $get('news_auto_refresh_enabled'),
                                seconds: $get('news_auto_refresh_interval_seconds'),
                            ))
                            ->columnSpanFull(),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(AdminSettingsService $settings): void
    {
        /** @var array{news_auto_refresh_enabled:bool,news_auto_refresh_interval_seconds:int|string|null} $state */
        $state = $this->getFormSchema()->getState();

        $saved = $settings->persistNewsAutoRefreshDefaults(
            enabled: $state['news_auto_refresh_enabled'],
            seconds: $state['news_auto_refresh_interval_seconds'],
        );

        $this->getFormSchema()->fill([
            'news_auto_refresh_enabled' => $saved->news_auto_refresh_enabled,
            'news_auto_refresh_interval_seconds' => $saved->news_auto_refresh_interval_seconds,
        ]);

        Notification::make()
            ->title('Admin settings saved')
            ->body('Настройки автообновления для News сохранены в базе.')
            ->success()
            ->send();
    }

    #[Override]
    public function getMaxContentWidth(): Width
    {
        return Width::Large;
    }

    private function newsAutoRefreshSummary(bool $enabled, mixed $seconds): string
    {
        if (! $enabled) {
            return 'По умолчанию автообновление News выключено.';
        }

        $seconds = is_numeric($seconds) ? (int) $seconds : app(AdminSettingsService::class)->defaultNewsAutoRefreshSeconds();
        $label = app(AdminSettingsService::class)->newsAutoRefreshIntervalSecondsOptions()[$seconds] ?? sprintf('%d sec', $seconds);

        return sprintf('По умолчанию страница News будет обновляться каждые %s.', $label);
    }

    private function getFormSchema(): Schema
    {
        $schema = $this->getSchema('form');

        if (! $schema instanceof Schema) {
            throw new LogicException('Admin Settings form schema is not configured.');
        }

        return $schema;
    }
}
