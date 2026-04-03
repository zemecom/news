<?php

declare(strict_types=1);

namespace Modules\Intelligence\Infrastructure\Codex;

use Modules\Intelligence\Domain\DTO\AiProviderProfile;

interface CodexAppServerClientContract
{
    /**
     * @return array<string, mixed>
     */
    public function startLogin(AiProviderProfile $account): array;

    /**
     * @return array<string, mixed>
     */
    public function cancelLogin(AiProviderProfile $account): array;

    /**
     * @return array<string, mixed>
     */
    public function readAccount(AiProviderProfile $account): array;

    /**
     * @return array<string, mixed>
     */
    public function readRateLimits(AiProviderProfile $account): array;

    public function logout(AiProviderProfile $account): void;
}
