<?php

declare(strict_types=1);

namespace Modules\Intelligence\Domain\Contracts;

use Modules\Intelligence\Domain\DTO\AiProviderProfile;

interface AiProviderStatusManager
{
    public function sync(AiProviderProfile $account): void;

    /**
     * @param  array<string, mixed>|null  $snapshot
     */
    public function markUsageLimited(AiProviderProfile $account, ?array $snapshot = null, ?string $message = null): void;

    public function markNotAuthenticated(AiProviderProfile $account): void;

    public function markError(AiProviderProfile $account, string $message): void;
}
