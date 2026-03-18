<?php

declare(strict_types=1);

namespace Modules\Intelligence\Application\Services;

use Modules\Intelligence\Domain\Contracts\AiProviderAccountRepository;
use Modules\Intelligence\Domain\Contracts\AiProviderStatusManager;
use Modules\Intelligence\Domain\DTO\AiProviderProfile;
use Throwable;

final readonly class SyncAiProviderStatsAction
{
    public function __construct(
        private AiProviderAccountRepository $accounts,
        private AiProviderStatusManager $statusManager,
    ) {}

    /**
     * @return array{checked:int,updated:int,failed:int}
     */
    public function run(string $provider = AiProviderProfile::PROVIDER_CHATGPT_CODEX): array
    {
        $summary = [
            'checked' => 0,
            'updated' => 0,
            'failed' => 0,
        ];

        foreach ($this->accounts->findEnabledByProvider($provider) as $account) {
            $summary['checked']++;

            try {
                $this->statusManager->sync($account);
                $summary['updated']++;
            } catch (Throwable $e) {
                $summary['failed']++;
                report($e);

                try {
                    $this->statusManager->markError($account, 'Stats sync failed: '.$e->getMessage());
                } catch (Throwable $markErrorException) {
                    report($markErrorException);
                }
            }
        }

        return $summary;
    }
}
