<?php

declare(strict_types=1);

namespace Modules\Intelligence\Application\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Modules\Intelligence\Application\Pipeline\NewsProcessingPipeline;
use Modules\Intelligence\Application\Queue\Middleware\ProviderConcurrencyMiddleware;
use Modules\Shared\Domain\Events\RawNewsCreated;

final class ProcessRawNewsListener implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Попытки выполнения
     */
    public int $tries = 5;

    /**
     * Бекофф (ожидание между попытками)
     *
     * @var array<int, int>
     */
    public array $backoff = [5, 15, 60, 120, 300];

    public function __construct(
        private readonly NewsProcessingPipeline $pipeline,
    ) {}

    public function viaQueue(): string
    {
        return 'intelligence_tasks';
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            app()->make(ProviderConcurrencyMiddleware::class),
        ];
    }

    public function handle(RawNewsCreated $event): void
    {
        $this->pipeline->handle($event->raw);
    }
}
