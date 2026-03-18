<?php

declare(strict_types=1);

namespace Modules\Intelligence\Application\Services;

use Modules\Intelligence\Domain\Contracts\AiProviderAccountRepository;
use Modules\Intelligence\Domain\DTO\AiProviderProfile;

final class ActiveAiProviderResolver
{
    public function __construct(
        private readonly AiProviderAccountRepository $accounts,
    ) {}

    public function resolveChatGptCodex(): ?AiProviderProfile
    {
        return $this->accounts->findFirstEnabledByProvider(AiProviderProfile::PROVIDER_CHATGPT_CODEX);
    }
}
