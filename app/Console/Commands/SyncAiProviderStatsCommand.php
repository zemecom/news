<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Modules\Intelligence\Application\Services\SyncAiProviderStatsAction;
use Modules\Intelligence\Domain\DTO\AiProviderProfile;

final class SyncAiProviderStatsCommand extends Command
{
    protected $signature = 'ai-providers:sync-stats {--provider='.AiProviderProfile::PROVIDER_CHATGPT_CODEX.' : AI provider slug to sync}';

    protected $description = 'Refresh AI provider auth status and subscription rate limits';

    public function handle(SyncAiProviderStatsAction $syncStats): int
    {
        $providerOption = $this->option('provider');
        $provider = is_string($providerOption) && $providerOption !== ''
            ? $providerOption
            : AiProviderProfile::PROVIDER_CHATGPT_CODEX;
        $summary = $syncStats->run($provider);

        if ($summary['checked'] === 0) {
            $this->info(sprintf('No enabled AI providers found for `%s`.', $provider));

            return self::SUCCESS;
        }

        $message = sprintf(
            'Synced stats for %d provider(s): updated=%d, failed=%d.',
            $summary['checked'],
            $summary['updated'],
            $summary['failed'],
        );

        if ($summary['failed'] > 0) {
            $this->warn($message);
        } else {
            $this->info($message);
        }

        return self::SUCCESS;
    }
}
