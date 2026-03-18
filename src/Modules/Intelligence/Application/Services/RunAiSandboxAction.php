<?php

declare(strict_types=1);

namespace Modules\Intelligence\Application\Services;

use Carbon\CarbonImmutable;
use Modules\Intelligence\Domain\Contracts\AiProviderStatusManager;
use Modules\Intelligence\Domain\Contracts\NewsAnalyzer;
use Modules\Intelligence\Domain\DTO\AiProviderProfile;
use Modules\Intelligence\Domain\DTO\NewsAnalysisResult;
use Modules\Intelligence\Domain\Exceptions\AiProviderException;
use Modules\Intelligence\Domain\Exceptions\AiProviderRateLimitException;
use Modules\Intelligence\Domain\Exceptions\AiProviderUnauthorizedException;
use Modules\Shared\Domain\DTO\RawNewsData;

final readonly class RunAiSandboxAction
{
    public function __construct(
        private NewsAnalyzer $analyzer,
        private AiProviderStatusManager $statusManager,
    ) {}

    public function run(
        AiProviderProfile $account,
        string $title,
        string $content,
        string $language,
        string $link,
    ): NewsAnalysisResult {
        $raw = new RawNewsData(
            sourceId: 0,
            externalId: null,
            title: $title,
            link: $link,
            content: $content,
            publishedAt: CarbonImmutable::now(),
            language: $language,
            metadata: [
                'sandbox' => true,
                'sandbox_requested_at' => now()->toIso8601String(),
            ],
            imageUrl: null,
            media: [],
            fingerprint: hash('sha256', implode('|', [$account->slug, $language, $title, $content])),
            rawId: null,
        );

        try {
            return $this->analyzer->analyze($raw, $account);
        } catch (AiProviderRateLimitException $e) {
            $this->statusManager->markUsageLimited($account, message: $e->getMessage());

            throw $e;
        } catch (AiProviderUnauthorizedException $e) {
            $this->statusManager->markNotAuthenticated($account);

            throw $e;
        } catch (AiProviderException $e) {
            $this->statusManager->markError($account, 'Sandbox failed: '.$e->getMessage());

            throw $e;
        }
    }
}
