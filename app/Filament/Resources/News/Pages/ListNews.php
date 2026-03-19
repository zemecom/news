<?php

declare(strict_types=1);

namespace App\Filament\Resources\News\Pages;

use App\Filament\Resources\News\NewsResource;
use App\Services\AdminSettingsService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Modules\Intelligence\Application\Services\EnqueueNewsAnalysisAction;
use Override;

final class ListNews extends ListRecords
{
    protected static string $resource = NewsResource::class;

    protected string $view = 'filament.resources.news.pages.list-news';

    public string $newsAutoRefreshSelection = AdminSettingsService::NEWS_AUTO_REFRESH_DEFAULT;

    public function mount(): void
    {
        parent::mount();

        $this->newsAutoRefreshSelection = app(AdminSettingsService::class)
            ->normalizeNewsAutoRefreshSelection(session()->get(AdminSettingsService::NEWS_AUTO_REFRESH_SESSION_KEY));
    }

    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            Action::make('news_auto_refresh')
                ->label(fn (): string => sprintf(
                    'Auto-refresh: %s',
                    app(AdminSettingsService::class)->newsAutoRefreshSelectionLabel($this->newsAutoRefreshSelection),
                ))
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->fillForm(fn (): array => [
                    'selection' => $this->newsAutoRefreshSelection,
                ])
                ->form([
                    Select::make('selection')
                        ->label('Refresh interval')
                        ->options(app(AdminSettingsService::class)->newsAutoRefreshSelectionOptions())
                        ->native(false)
                        ->required(),
                ])
                ->modalHeading('News auto-refresh')
                ->modalDescription('Интервал применяется только к текущей admin-сессии. Значение Default берётся из Admin Settings.')
                ->modalSubmitActionLabel('Apply')
                ->action(function (array $data): void {
                    $this->setNewsAutoRefreshSelection((string) ($data['selection'] ?? AdminSettingsService::NEWS_AUTO_REFRESH_DEFAULT));
                }),
            Action::make('enrich_missing_ai_metadata')
                ->label('Enrich missing AI metadata')
                ->icon('heroicon-o-sparkles')
                ->color('warning')
                ->requiresConfirmation()
                ->modalDescription('Возьмёт текущий filtered query и поставит в очередь только новости без сохранённого AI analysis.provider.')
                ->action(function (): void {
                    $count = $this->enqueueFilteredNews(onlyMissingAiMetadata: true);

                    if ($count === null) {
                        return;
                    }

                    Notification::make()
                        ->title($count > 0 ? 'News queued for AI enrichment' : 'Nothing to enqueue')
                        ->body($count > 0
                            ? sprintf('%d новостей без AI metadata поставлено в intelligence_tasks.', $count)
                            : 'По текущим фильтрам не найдено новостей без AI analysis.provider.')
                        ->color($count > 0 ? 'success' : 'gray')
                        ->send();
                }),
            Action::make('refresh_ai_metadata')
                ->label('Refresh AI metadata')
                ->icon('heroicon-o-arrow-path')
                ->color('info')
                ->requiresConfirmation()
                ->modalDescription('Возьмёт текущий filtered query и поставит в очередь все найденные новости, включая уже проанализированные.')
                ->action(function (): void {
                    $count = $this->enqueueFilteredNews(onlyMissingAiMetadata: false);

                    if ($count === null) {
                        return;
                    }

                    Notification::make()
                        ->title($count > 0 ? 'News queued for AI refresh' : 'Nothing to enqueue')
                        ->body($count > 0
                            ? sprintf('%d новостей поставлено в intelligence_tasks для обновления AI metadata.', $count)
                            : 'По текущим фильтрам не найдено новостей для переобогащения AI metadata.')
                        ->color($count > 0 ? 'success' : 'gray')
                        ->send();
                }),
        ];
    }

    #[Override]
    public function getHeader(): View
    {
        return view('filament.resources.news.partials.page-header', [
            'breadcrumbs' => filament()->hasBreadcrumbs() ? $this->getBreadcrumbs() : [],
            'heading' => $this->getHeading(),
            'subheading' => $this->getSubheading(),
            'headerActions' => $this->getCachedHeaderActions(),
            'headerActionsAlignment' => $this->getHeaderActionsAlignment(),
            'tableFiltersForm' => $this->getTable()->getFiltersForm(),
        ]);
    }

    #[Override]
    public function getMaxContentWidth(): Width
    {
        return Width::Full;
    }

    public function setNewsAutoRefreshSelection(string $selection): void
    {
        $settings = app(AdminSettingsService::class);

        $this->newsAutoRefreshSelection = $settings->normalizeNewsAutoRefreshSelection($selection);
        session()->put(AdminSettingsService::NEWS_AUTO_REFRESH_SESSION_KEY, $this->newsAutoRefreshSelection);

        Notification::make()
            ->title('Auto-refresh updated')
            ->body(sprintf(
                'Текущее автообновление News: %s.',
                $settings->newsAutoRefreshSelectionLabel($this->newsAutoRefreshSelection),
            ))
            ->success()
            ->send();
    }

    public function newsAutoRefreshInterval(): ?string
    {
        return app(AdminSettingsService::class)
            ->resolveNewsAutoRefreshInterval($this->newsAutoRefreshSelection);
    }

    private function enqueueFilteredNews(bool $onlyMissingAiMetadata): ?int
    {
        $query = $this->getFilteredTableQuery();

        if (! $query instanceof Builder) {
            Notification::make()
                ->title('No filtered query available')
                ->body('Не удалось получить текущий набор записей таблицы.')
                ->warning()
                ->send();

            return null;
        }

        $action = app(EnqueueNewsAnalysisAction::class);
        $count = 0;

        $filteredQuery = clone $query;

        if ($onlyMissingAiMetadata) {
            $filteredQuery->whereNull('source_metadata->analysis->provider');
        }

        $filteredQuery
            ->select('id')
            ->chunkById(100, function ($records) use ($action, &$count): void {
                $count += $action->enqueueMany(collect($records)->pluck('id')->all());
            });

        return $count;
    }

    public function refreshNewsPage(): void {}
}
