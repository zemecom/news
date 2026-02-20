<?php

declare(strict_types=1);

namespace Modules\Catalog\Application\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Catalog\Application\Actions\PreloadNewsMediaAction;

final class PreloadNewsMediaJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [10, 60, 300];

    public function __construct(
        public readonly int $newsItemId,
    ) {
        $this->onQueue('media_tasks');
    }

    public function handle(PreloadNewsMediaAction $preloadNewsMedia): void
    {
        $preloadNewsMedia($this->newsItemId);
    }
}
