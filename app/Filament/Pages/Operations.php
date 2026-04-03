<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Widgets\AiProviderStatusWidget;
use App\Services\QueueManagementService;
use App\Services\QueueOverviewService;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Override;
use Throwable;
use UnitEnum;

final class Operations extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSignal;

    protected static ?string $navigationLabel = 'Operations';

    protected static string|UnitEnum|null $navigationGroup = 'Operations';

    protected static ?int $navigationSort = 20;

    protected static ?string $title = 'Operations';

    protected string $view = 'filament.pages.operations';

    public ?string $selectedQueue = null;

    public function mount(): void
    {
        $this->selectedQueue ??= app(QueueOverviewService::class)->queueNames()[0] ?? null;
    }

    #[Override]
    public function getMaxContentWidth(): Width
    {
        return Width::ScreenTwoExtraLarge;
    }

    /**
     * @return array<class-string<\Filament\Widgets\Widget>>
     */
    #[Override]
    protected function getHeaderWidgets(): array
    {
        return [
            AiProviderStatusWidget::class,
        ];
    }

    #[Override]
    public function getHeaderWidgetsColumns(): int
    {
        return 1;
    }

    /**
     * @return array<string, mixed>
     */
    #[Override]
    protected function getViewData(): array
    {
        $overview = app(QueueOverviewService::class);
        $selectedQueue = $this->resolveSelectedQueue($overview);
        $queuedJobs = [];
        $queuedJobsError = null;

        if (is_string($selectedQueue)) {
            try {
                $queuedJobs = app(QueueManagementService::class)->previewQueue($selectedQueue, 10);
            } catch (Throwable $e) {
                $queuedJobsError = $e->getMessage();
            }
        }

        return [
            'queueSummaries' => $overview->getQueueSummaries(),
            'failedJobs' => $overview->getRecentFailedJobs(),
            'selectedQueue' => $selectedQueue,
            'queuedJobs' => $queuedJobs,
            'queuedJobsError' => $queuedJobsError,
        ];
    }

    public function selectQueue(string $queueName): void
    {
        $this->selectedQueue = $this->resolveQueueName($queueName);
    }

    public function processOneQueueJob(string $queueName): void
    {
        try {
            $resolvedQueueName = $this->resolveQueueName($queueName);

            if ($resolvedQueueName === null) {
                Notification::make()
                    ->title('Queue processing failed')
                    ->body('Не удалось определить очередь для one-step обработки.')
                    ->danger()
                    ->send();

                return;
            }

            $result = app(QueueManagementService::class)->processOneQueueJob($resolvedQueueName);

            Notification::make()
                ->title($result['processed'] ? 'Queue worker finished one-step run' : 'Queue is empty')
                ->body($result['processed']
                    ? sprintf(
                        'Команда обработала до одной задачи из `%s`. Было: %s, стало: %s.',
                        $result['queue'],
                        $result['before'] ?? 'n/a',
                        $result['after'] ?? 'n/a',
                    )
                    : sprintf('В `%s` сейчас нет задач для one-step обработки.', $result['queue']))
                ->color($result['processed'] ? 'success' : 'gray')
                ->send();
        } catch (Throwable $e) {
            Notification::make()
                ->title('Queue processing failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function purgeQueue(string $queueName): void
    {
        try {
            $resolvedQueueName = $this->resolveQueueName($queueName);

            if ($resolvedQueueName === null) {
                Notification::make()
                    ->title('Queue purge failed')
                    ->body('Не удалось определить очередь для очистки.')
                    ->danger()
                    ->send();

                return;
            }

            $removed = app(QueueManagementService::class)->purgeQueue($resolvedQueueName);

            Notification::make()
                ->title('Queue purged')
                ->body(sprintf('Из `%s` удалено сообщений: %d.', $resolvedQueueName, $removed))
                ->success()
                ->send();
        } catch (Throwable $e) {
            Notification::make()
                ->title('Queue purge failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function refreshOperationsPage(): void {}

    private function resolveSelectedQueue(QueueOverviewService $overview): ?string
    {
        $this->selectedQueue = $this->resolveQueueName($this->selectedQueue, $overview);

        return $this->selectedQueue;
    }

    private function resolveQueueName(?string $queueName, ?QueueOverviewService $overview = null): ?string
    {
        $queueNames = ($overview ?? app(QueueOverviewService::class))->queueNames();

        if ($queueName !== null && in_array($queueName, $queueNames, true)) {
            return $queueName;
        }

        return $queueNames[0] ?? null;
    }
}
