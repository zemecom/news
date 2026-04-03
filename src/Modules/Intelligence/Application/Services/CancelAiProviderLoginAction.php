<?php

declare(strict_types=1);

namespace Modules\Intelligence\Application\Services;

use Modules\Intelligence\Domain\Contracts\AiProviderAuthManager;
use Modules\Intelligence\Domain\DTO\AiProviderProfile;

final readonly class CancelAiProviderLoginAction
{
    public function __construct(
        private AiProviderAuthManager $authManager,
    ) {}

    public function run(AiProviderProfile $account): void
    {
        $this->authManager->cancelLogin($account);
    }
}
