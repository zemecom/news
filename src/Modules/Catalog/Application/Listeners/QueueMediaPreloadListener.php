<?php

declare(strict_types=1);

namespace Modules\Catalog\Application\Listeners;

use Modules\Catalog\Application\Jobs\PreloadNewsMediaJob;
use Modules\Shared\Domain\Events\NewsEnriched;

final readonly class QueueMediaPreloadListener
{
    public function handle(NewsEnriched $event): void
    {
        dispatch(new PreloadNewsMediaJob($event->rawId));
    }
}
