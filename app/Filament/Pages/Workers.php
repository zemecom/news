<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Services\WorkerManagementService;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Override;
use Throwable;
use UnitEnum;

final class Workers extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCpuChip;

    protected static ?string $navigationLabel = 'Workers';

    protected static ?string $slug = 'workers';

    protected static string|UnitEnum|null $navigationGroup = 'Operations';

    protected static ?int $navigationSort = 19;

    protected static ?string $title = 'Workers';

    protected string $view = 'filament.pages.workers';

    public ?string $selectedRuntime = null;

    public ?string $selectedQueue = null;

    public function mount(): void
    {
        $firstWorker = app(WorkerManagementService::class)->listWorkers()[0] ?? null;

        if (is_array($firstWorker)) {
            $this->selectedRuntime ??= is_string($firstWorker['runtime'] ?? null) ? $firstWorker['runtime'] : null;
            $this->selectedQueue ??= is_string($firstWorker['queue'] ?? null) ? $firstWorker['queue'] : null;
        }
    }

    #[Override]
    public function getMaxContentWidth(): Width
    {
        return Width::ScreenTwoExtraLarge;
    }

    /**
     * @return array<string, mixed>
     */
    #[Override]
    protected function getViewData(): array
    {
        $service = app(WorkerManagementService::class);
        $workers = $service->listWorkers();
        $selectedWorker = $this->resolveSelectedWorker($workers);
        $selectedRuntime = is_array($selectedWorker) && is_string($selectedWorker['runtime'] ?? null)
            ? $selectedWorker['runtime']
            : null;
        $selectedQueue = $this->resolveSelectedQueue($selectedWorker);

        $this->selectedRuntime = $selectedRuntime;
        $this->selectedQueue = $selectedQueue;

        return [
            'workers' => $workers,
            'selectedRuntime' => $selectedRuntime,
            'selectedQueue' => $selectedQueue,
            'dockerControl' => $service->dockerControlStatus(),
            'failedJobs' => $service->recentFailedJobs(),
            'queuedJobs' => $service->previewQueue($selectedQueue),
            'logTail' => $selectedRuntime !== null ? $service->tailLogs($selectedRuntime) : ['lines' => [], 'error' => null],
        ];
    }

    public function selectRuntime(string $runtime): void
    {
        foreach (app(WorkerManagementService::class)->listWorkers() as $worker) {
            if (($worker['runtime'] ?? null) === $runtime) {
                $this->selectedRuntime = $runtime;
                $this->selectedQueue = is_string($worker['queue'] ?? null) ? $worker['queue'] : null;

                return;
            }
        }
    }

    public function selectQueue(string $queue): void
    {
        $selectedWorker = $this->resolveSelectedWorker(app(WorkerManagementService::class)->listWorkers());

        if (! is_array($selectedWorker)) {
            return;
        }

        $queues = is_array($selectedWorker['queues'] ?? null) ? $selectedWorker['queues'] : [];

        if (in_array($queue, $queues, true)) {
            $this->selectedQueue = $queue;
        }
    }

    public function startWorker(string $runtime): void
    {
        $this->notifyAction(app(WorkerManagementService::class)->startWorker($runtime), 'Worker started', 'Worker start failed');
    }

    public function stopWorker(string $runtime): void
    {
        $this->notifyAction(app(WorkerManagementService::class)->stopWorker($runtime), 'Worker stopped', 'Worker stop failed');
    }

    public function restartWorker(string $runtime): void
    {
        $this->notifyAction(app(WorkerManagementService::class)->restartWorker($runtime), 'Worker restarted', 'Worker restart failed');
    }

    public function softRestartWorkers(): void
    {
        $this->notifyAction(app(WorkerManagementService::class)->softRestartWorkers(), 'Laravel workers restart broadcasted', 'Laravel workers restart failed');
    }

    public function runOneJob(string $queue): void
    {
        $this->notifyAction(app(WorkerManagementService::class)->runOneJob($queue), 'Queue worker processed one job', 'Queue processing failed');
    }

    public function runUntilEmpty(string $queue, ?int $maxJobs = null): void
    {
        $this->notifyAction(app(WorkerManagementService::class)->runUntilEmpty($queue, $maxJobs), 'Queue drained with safe limit', 'Queue drain failed');
    }

    public function purgeQueue(string $queue): void
    {
        $this->notifyAction(app(WorkerManagementService::class)->purgeQueue($queue), 'Queue purged', 'Queue purge failed');
    }

    public function refreshWorkersPage(): void {}

    /**
     * @param  list<array<string, mixed>>  $workers
     * @return array<string, mixed>|null
     */
    private function resolveSelectedWorker(array $workers): ?array
    {
        foreach ($workers as $worker) {
            if (($worker['runtime'] ?? null) === $this->selectedRuntime) {
                return $worker;
            }
        }

        return $workers[0] ?? null;
    }

    /**
     * @param  array<string, mixed>|null  $worker
     */
    private function resolveSelectedQueue(?array $worker): ?string
    {
        if (! is_array($worker)) {
            return null;
        }

        $queues = array_values(array_filter(
            is_array($worker['queues'] ?? null) ? $worker['queues'] : [],
            static fn (mixed $queue): bool => is_string($queue) && $queue !== '',
        ));

        if ($this->selectedQueue !== null && in_array($this->selectedQueue, $queues, true)) {
            return $this->selectedQueue;
        }

        return is_string($worker['queue'] ?? null) ? $worker['queue'] : ($queues[0] ?? null);
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function notifyAction(array $result, string $successTitle, string $failureTitle): void
    {
        try {
            $success = (bool) ($result['success'] ?? $result['processed'] ?? false);
            $message = is_string($result['message'] ?? null)
                ? $result['message']
                : (is_string($result['queue'] ?? null) ? (string) $result['queue'] : 'Worker action finished.');

            Notification::make()
                ->title($success ? $successTitle : $failureTitle)
                ->body($message)
                ->color($success ? 'success' : 'danger')
                ->send();
        } catch (Throwable $e) {
            Notification::make()
                ->title($failureTitle)
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}
