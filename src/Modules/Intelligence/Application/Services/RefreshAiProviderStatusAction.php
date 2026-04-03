<?php

declare(strict_types=1);

namespace Modules\Intelligence\Application\Services;

use Modules\Intelligence\Domain\Contracts\AiProviderStatusManager;
use Modules\Intelligence\Domain\DTO\AiProviderProfile;

final readonly class RefreshAiProviderStatusAction
{
    public function __construct(
        private AiProviderStatusManager $statusManager,
    ) {}

    public function run(AiProviderProfile $account): void
    {
        $this->statusManager->sync($account);
    }
}
