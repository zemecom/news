<?php

declare(strict_types=1);

namespace Modules\Catalog\Application\Listeners;

use Modules\Catalog\Domain\Contracts\SourceRepository;
use Modules\Shared\Domain\Events\SourceFetchFailed;
use Modules\Shared\Domain\Events\SourceFetchSucceeded;

final readonly class UpdateSourceStatusListener
{
    public function __construct(
        private SourceRepository $sourceRepository,
    ) {}

    /**
     * Handle the event.
     */
    public function handle(object $event): void
    {
        if ($event instanceof SourceFetchSucceeded) {
            $this->sourceRepository->updateSuccess($event->sourceId);
        } elseif ($event instanceof SourceFetchFailed) {
            $this->sourceRepository->updateFailure($event->sourceId);
        }
    }
}
