<?php

declare(strict_types=1);

namespace Modules\Intelligence\Domain\Contracts;

use Modules\Intelligence\Domain\DTO\AiProviderProfile;

interface AiProviderAccountRepository
{
    public function findFirstEnabledByProvider(string $provider): ?AiProviderProfile;

    /**
     * @return array<int, AiProviderProfile>
     */
    public function findEnabledByProvider(string $provider): array;
}
