<?php

declare(strict_types=1);

namespace Modules\Crawler\Application\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Shared\Domain\DTO\RawNewsData;
use Modules\Shared\Domain\Events\RawNewsCreated;

final class ProcessNewsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 5;

    /** @var array<int, int> */
    public array $backoff = [5, 15, 60, 120, 300];

    public function __construct(
        public readonly RawNewsData $raw,
    ) {
        $this->onQueue('intelligence_tasks');
    }

    public function handle(Dispatcher $events): void
    {
        $events->dispatch(new RawNewsCreated($this->raw));
    }
}
