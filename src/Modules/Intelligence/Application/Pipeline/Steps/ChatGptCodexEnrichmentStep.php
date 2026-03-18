<?php

declare(strict_types=1);

namespace Modules\Intelligence\Application\Pipeline\Steps;

use Illuminate\Support\Facades\Log;
use Modules\Intelligence\Application\Services\ActiveAiProviderResolver;
use Modules\Intelligence\Domain\Contracts\AiProviderStatusManager;
use Modules\Intelligence\Domain\Contracts\NewsAnalyzer;
use Modules\Intelligence\Domain\Exceptions\AiProviderException;
use Modules\Intelligence\Domain\Exceptions\AiProviderRateLimitException;
use Modules\Intelligence\Domain\Exceptions\AiProviderUnauthorizedException;
use Modules\Shared\Domain\DTO\EnrichedNewsData;
use Modules\Shared\Domain\DTO\RawNewsData;

final readonly class ChatGptCodexEnrichmentStep implements PipelineStep
{
    public function __construct(
        private NewsAnalyzer $analyzer,
        private ActiveAiProviderResolver $resolver,
        private AiProviderStatusManager $statusSynchronizer,
    ) {}

    public function process(RawNewsData|EnrichedNewsData $input): RawNewsData|EnrichedNewsData
    {
        if (! $input instanceof RawNewsData) {
            return $input;
        }

        if ((string) config('intelligence.provider', 'chatgpt_codex') !== 'chatgpt_codex') {
            return $input;
        }

        $account = $this->resolver->resolveChatGptCodex();
        if ($account === null || ! $account->enabled || ! $account->isAuthenticated()) {
            return $input;
        }

        try {
            $analysis = $this->analyzer->analyze($input, $account);
        } catch (AiProviderRateLimitException $e) {
            $this->statusSynchronizer->markUsageLimited($account, message: $e->getMessage());
            $this->logFallback($input, $e);

            return $input;
        } catch (AiProviderUnauthorizedException $e) {
            $this->statusSynchronizer->markNotAuthenticated($account);
            $this->logFallback($input, $e);

            return $input;
        } catch (AiProviderException $e) {
            $this->statusSynchronizer->markError($account, $e->getMessage());
            $this->logFallback($input, $e);

            return $input;
        }

        $metadata = array_merge($input->metadata, [
            'category' => $analysis->category,
            'tags' => $analysis->tags,
            'sentiment' => $analysis->sentiment,
            'title_generated' => $analysis->generatedTitle,
            'analysis' => $analysis->analysisMetadata,
        ]);

        $overrides = [
            'metadata' => $metadata,
        ];

        if ($analysis->translatedContent !== '' && $analysis->translatedContent !== $input->content) {
            $overrides['content'] = $analysis->translatedContent;
            $overrides['language'] = 'ru';
            $overrides['metadata'] = array_merge($metadata, [
                'original_content' => $input->metadata['original_content'] ?? $input->content,
                'original_language' => $input->metadata['original_language'] ?? $input->language,
            ]);
        }

        return $input->with($overrides);
    }

    private function logFallback(RawNewsData $input, AiProviderException $e): void
    {
        Log::warning('ChatGPT Codex enrichment failed, falling back to heuristics.', [
            'fingerprint' => $input->fingerprint,
            'source_id' => $input->sourceId,
            'error' => $e->getMessage(),
        ]);
    }
}
