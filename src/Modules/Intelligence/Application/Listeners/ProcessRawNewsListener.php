<?php

declare(strict_types=1);

namespace Modules\Intelligence\Application\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Modules\Intelligence\Application\Pipeline\NewsProcessingPipeline;
use Modules\Shared\Domain\Events\RawNewsCreated;

final class ProcessRawNewsListener implements ShouldQueue
{
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

    public function handle(RawNewsCreated $event): void
    {
        $this->pipeline->handle($event->raw);
    }
}
