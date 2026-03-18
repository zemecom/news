<?php

declare(strict_types=1);

namespace Modules\Intelligence\Infrastructure\Persistence;

use Modules\Intelligence\Domain\Contracts\AiProviderAccountRepository;
use Modules\Intelligence\Domain\DTO\AiProviderProfile;
use Modules\Intelligence\Infrastructure\Persistence\Models\AiProviderAccount;

final class EloquentAiProviderAccountRepository implements AiProviderAccountRepository
{
    public function findFirstEnabledByProvider(string $provider): ?AiProviderProfile
    {
        /** @var AiProviderProfile|null $account */
        $account = $this->findEnabledByProvider($provider)[0] ?? null;

        return $account;
    }

    /**
     * @return array<int, AiProviderProfile>
     */
    public function findEnabledByProvider(string $provider): array
    {
        /** @var array<int, AiProviderAccount> $accounts */
        $accounts = AiProviderAccount::query()
            ->where('provider', $provider)
            ->where('is_enabled', true)
            ->orderBy('id')
            ->get()
            ->all();

        return array_values(array_map(
            static fn (AiProviderAccount $account): AiProviderProfile => $account->toProfile(),
            $accounts,
        ));
    }
}
