<?php

declare(strict_types=1);

namespace Modules\Intelligence\Application\Pipeline\Steps;

use Illuminate\Support\Facades\Log;
use Modules\Intelligence\Application\Services\ActiveAiProviderResolver;
use Modules\Intelligence\Application\Services\NewsAnalysisRuntimeRecorder;
use Modules\Intelligence\Domain\Contracts\AiProviderStatusManager;
use Modules\Intelligence\Domain\Contracts\NewsAnalyzer;
use Modules\Intelligence\Domain\DTO\AiProviderProfile;
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
        private NewsAnalysisRuntimeRecorder $runtimeRecorder,
    ) {}

    public function process(RawNewsData|EnrichedNewsData $input): RawNewsData|EnrichedNewsData
    {
        if (! $input instanceof RawNewsData) {
            return $input;
        }

        if ((string) config('intelligence.provider', 'chatgpt_codex') !== 'chatgpt_codex') {
            $this->markFallback($input, 'provider_unavailable', 'AI provider is switched off in configuration.');

            return $input;
        }

        $account = $this->resolver->resolveChatGptCodex();
        if ($account === null || ! $account->enabled || ! $account->isAuthenticated()) {
            $this->markFallback($input, 'provider_unavailable', 'ChatGPT Codex provider is unavailable or not authenticated.', $account);

            return $input;
        }

        try {
            $analysis = $this->analyzer->analyze($input, $account);
        } catch (AiProviderRateLimitException $e) {
            $this->statusSynchronizer->markUsageLimited($account, message: $e->getMessage());
            $this->logFallback($input, $e);
            $this->markFallback($input, 'rate_limited', $e->getMessage(), $account);

            return $input;
        } catch (AiProviderUnauthorizedException $e) {
            $this->statusSynchronizer->markNotAuthenticated($account);
            $this->logFallback($input, $e);
            $this->markFallback($input, 'unauthorized', $e->getMessage(), $account);

            return $input;
        } catch (AiProviderException $e) {
            $this->statusSynchronizer->markError($account, $e->getMessage());
            $this->logFallback($input, $e);
            $this->markFallback($input, 'provider_error', $e->getMessage(), $account);

            return $input;
        }

        if ($input->rawId !== null) {
            $this->runtimeRecorder->markAiSuccess($input->rawId, $account);
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

    private function markFallback(
        RawNewsData $input,
        string $reason,
        ?string $message,
        ?AiProviderProfile $account = null,
    ): void {
        if ($input->rawId === null) {
            return;
        }

        $this->runtimeRecorder->markFallback(
            newsItemId: $input->rawId,
            reason: $reason,
            message: $message,
            provider: $account,
        );
    }
}
