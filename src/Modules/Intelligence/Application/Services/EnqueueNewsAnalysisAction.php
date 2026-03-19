<?php

declare(strict_types=1);

namespace Modules\Intelligence\Application\Services;

use Illuminate\Contracts\Events\Dispatcher;
use Modules\Shared\Domain\Contracts\NewsStore;
use Modules\Shared\Domain\Events\RawNewsCreated;

final readonly class EnqueueNewsAnalysisAction
{
    public function __construct(
        private NewsStore $news,
        private NewsAnalysisRuntimeRecorder $runtimeRecorder,
        private Dispatcher $events,
    ) {}

    public function enqueue(int $newsItemId): bool
    {
        $raw = $this->news->findRawById($newsItemId);

        if ($raw === null) {
            return false;
        }

        $this->runtimeRecorder->queue($newsItemId);
        $this->events->dispatch(new RawNewsCreated($raw));

        return true;
    }

    /**
     * @param  iterable<int>  $newsItemIds
     */
    public function enqueueMany(iterable $newsItemIds): int
    {
        $enqueued = 0;
        $seen = [];

        foreach ($newsItemIds as $newsItemId) {
            $id = (int) $newsItemId;

            if ($id <= 0 || isset($seen[$id])) {
                continue;
            }

            $seen[$id] = true;

            if ($this->enqueue($id)) {
                $enqueued++;
            }
        }

        return $enqueued;
    }
}
