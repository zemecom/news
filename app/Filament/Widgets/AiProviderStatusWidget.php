<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Filament\Resources\AiProviderAccounts\AiProviderAccountResource;
use App\Filament\Support\AiProviderStatsAutoRefresher;
use Filament\Notifications\Notification;
use Filament\Widgets\Widget;
use Modules\Intelligence\Application\Services\SyncAiProviderStatsAction;
use Modules\Intelligence\Infrastructure\Persistence\Models\AiProviderAccount;
use Throwable;

final class AiProviderStatusWidget extends Widget
{
    protected static bool $isLazy = false;

    protected string $view = 'filament.widgets.ai-provider-status-widget';

    protected int|string|array $columnSpan = 'full';

    public bool $isRefreshingProviderStats = false;

    public function mount(AiProviderStatsAutoRefresher $autoRefresher): void
    {
        $this->isRefreshingProviderStats = $autoRefresher->scheduleRefreshIfStale();
    }

    public function syncProviderStatisticsState(AiProviderStatsAutoRefresher $autoRefresher): void
    {
        $this->isRefreshingProviderStats = $autoRefresher->isRefreshing();
    }

    public function refreshProviderStatistics(): void
    {
        try {
            $summary = app(SyncAiProviderStatsAction::class)->run(AiProviderAccount::PROVIDER_CHATGPT_CODEX);
        } catch (Throwable $e) {
            Notification::make()
                ->title('Provider statistics refresh failed')
                ->body($e->getMessage())
                ->danger()
                ->send();

            return;
        }

        $title = $summary['failed'] > 0
            ? 'Provider statistics refreshed with warnings'
            : 'Provider statistics refreshed';

        $notification = Notification::make()
            ->title($title)
            ->body(sprintf(
                'Проверено %d provider accounts, обновлено %d, ошибок %d.',
                $summary['checked'],
                $summary['updated'],
                $summary['failed'],
            ));

        if ($summary['checked'] === 0) {
            $notification
                ->color('gray')
                ->body('Для ChatGPT Codex не найдено активных provider accounts.')
                ->send();

            return;
        }

        if ($summary['failed'] > 0) {
            $notification->warning()->send();

            return;
        }

        $this->isRefreshingProviderStats = false;

        $notification->success()->send();
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        /** @var AiProviderAccount|null $record */
        $record = AiProviderAccount::query()
            ->where('provider', AiProviderAccount::PROVIDER_CHATGPT_CODEX)
            ->where('is_enabled', true)
            ->orderBy('id')
            ->first();

        return [
            'record' => $record,
            'providerUrl' => AiProviderAccountResource::getUrl('index'),
        ];
    }
}
