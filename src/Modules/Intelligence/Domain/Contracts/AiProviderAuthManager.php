<?php

declare(strict_types=1);

namespace Modules\Intelligence\Domain\Contracts;

use Modules\Intelligence\Domain\DTO\AiProviderProfile;

interface AiProviderAuthManager
{
    public function startLogin(AiProviderProfile $account): void;

    public function cancelLogin(AiProviderProfile $account): void;

    public function logout(AiProviderProfile $account): void;
}
