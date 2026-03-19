<?php

declare(strict_types=1);

namespace Modules\Intelligence\Application\Services;

use Modules\Intelligence\Domain\DTO\AiProviderProfile;
use Modules\Shared\Domain\Contracts\NewsStore;

final readonly class NewsAnalysisRuntimeRecorder
{
    public function __construct(private NewsStore $news) {}

    /**
     * @return array<string, mixed>
     */
    public function queue(int $newsItemId): array
    {
        $current = $this->news->getAnalysisRuntime($newsItemId) ?? [];
        $timestamp = $this->timestamp();

        $runtime = [
            'status' => 'queued',
            'attempt' => max(0, (int) ($current['attempt'] ?? 0)) + 1,
            'queued_at' => $timestamp,
            'started_at' => null,
            'finished_at' => null,
            'provider' => null,
            'model' => null,
            'reasoning_effort' => null,
            'last_error' => null,
            'fallback_reason' => null,
            'timeline' => [
                $this->event('pipeline.queued', 'Queued for analysis', 'queued'),
            ],
        ];

        $this->news->putAnalysisRuntime($newsItemId, $runtime);

        return $runtime;
    }

    public function markRunning(int $newsItemId, ?AiProviderProfile $provider = null): void
    {
        $runtime = $this->news->getAnalysisRuntime($newsItemId) ?? [
            'status' => 'not_analyzed',
            'attempt' => 1,
            'queued_at' => $this->timestamp(),
            'timeline' => [],
        ];

        $hasStarted = filled($runtime['started_at'] ?? null);
        $isFallback = (string) ($runtime['status'] ?? '') === 'fallback';

        $runtime['status'] = $isFallback ? 'fallback' : 'running';
        $runtime['attempt'] = max(1, (int) ($runtime['attempt'] ?? 1));
        $runtime['queued_at'] ??= $this->timestamp();
        $runtime['started_at'] ??= $this->timestamp();
        $runtime['finished_at'] = null;

        if (! $isFallback) {
            $runtime['last_error'] = null;
            $runtime['fallback_reason'] = null;
        }

        $runtime['timeline'] = $this->normalizeTimeline($runtime['timeline'] ?? []);

        $runtime = $this->applyProviderContext($runtime, $provider);

        if (! $hasStarted) {
            $runtime['timeline'][] = $this->event('pipeline.started', 'Pipeline started', 'running');
        }

        $this->news->putAnalysisRuntime($newsItemId, $runtime);
    }

    public function recordStepStarted(int $newsItemId, string $stepKey, string $label): void
    {
        $runtime = $this->news->getAnalysisRuntime($newsItemId) ?? [];
        $runtime['timeline'] = $this->normalizeTimeline($runtime['timeline'] ?? []);
        $runtime['timeline'][] = $this->event($stepKey.'.started', $label, 'running');

        $this->news->putAnalysisRuntime($newsItemId, $runtime);
    }

    public function recordStepCompleted(int $newsItemId, string $stepKey, string $label, ?string $message = null): void
    {
        $runtime = $this->news->getAnalysisRuntime($newsItemId) ?? [];
        $runtime['timeline'] = $this->normalizeTimeline($runtime['timeline'] ?? []);
        $runtime['timeline'][] = $this->event($stepKey.'.completed', $label, 'completed', $message);

        $this->news->putAnalysisRuntime($newsItemId, $runtime);
    }

    public function recordStepFailed(int $newsItemId, string $stepKey, string $label, string $message): void
    {
        $runtime = $this->news->getAnalysisRuntime($newsItemId) ?? [];
        $runtime['timeline'] = $this->normalizeTimeline($runtime['timeline'] ?? []);
        $runtime['timeline'][] = $this->event($stepKey.'.failed', $label, 'failed', $message);
        $runtime['last_error'] = $message;

        $this->news->putAnalysisRuntime($newsItemId, $runtime);
    }

    public function markFallback(
        int $newsItemId,
        string $reason,
        ?string $message = null,
        ?AiProviderProfile $provider = null,
    ): void {
        $runtime = $this->news->getAnalysisRuntime($newsItemId) ?? [];
        $runtime['status'] = 'fallback';
        $runtime['fallback_reason'] = $reason;
        $runtime['last_error'] = $message;
        $runtime['timeline'] = $this->normalizeTimeline($runtime['timeline'] ?? []);
        $runtime = $this->applyProviderContext($runtime, $provider);
        $runtime['timeline'][] = $this->event(
            'ai.fallback',
            'AI fallback',
            'fallback',
            $message ?? str($reason)->replace('_', ' ')->headline()->toString(),
        );

        $this->news->putAnalysisRuntime($newsItemId, $runtime);
    }

    public function markAiSuccess(int $newsItemId, AiProviderProfile $provider): void
    {
        $runtime = $this->news->getAnalysisRuntime($newsItemId) ?? [];
        $runtime = $this->applyProviderContext($runtime, $provider);
        $runtime['timeline'] = $this->normalizeTimeline($runtime['timeline'] ?? []);
        $runtime['timeline'][] = $this->event(
            'ai.success',
            'AI enrichment completed',
            'completed',
            sprintf(
                '%s%s',
                $provider->provider,
                $provider->defaultModel !== '' ? ' · '.$provider->defaultModel : '',
            ),
        );

        $this->news->putAnalysisRuntime($newsItemId, $runtime);
    }

    public function markCompleted(int $newsItemId): void
    {
        $runtime = $this->news->getAnalysisRuntime($newsItemId) ?? [];
        $currentStatus = (string) ($runtime['status'] ?? 'completed');
        $isFallback = $currentStatus === 'fallback';

        $runtime['status'] = $isFallback ? 'fallback' : 'completed';
        $runtime['finished_at'] = $this->timestamp();
        $runtime['timeline'] = $this->normalizeTimeline($runtime['timeline'] ?? []);
        $runtime['timeline'][] = $this->event(
            'pipeline.finished',
            $isFallback ? 'Pipeline completed with fallback' : 'Pipeline completed',
            $isFallback ? 'fallback' : 'completed',
        );

        $this->news->putAnalysisRuntime($newsItemId, $runtime);
    }

    public function markFailed(int $newsItemId, string $message): void
    {
        $runtime = $this->news->getAnalysisRuntime($newsItemId) ?? [];
        $runtime['status'] = 'failed';
        $runtime['finished_at'] = $this->timestamp();
        $runtime['last_error'] = $message;
        $runtime['timeline'] = $this->normalizeTimeline($runtime['timeline'] ?? []);
        $runtime['timeline'][] = $this->event('pipeline.failed', 'Pipeline failed', 'failed', $message);

        $this->news->putAnalysisRuntime($newsItemId, $runtime);
    }

    /**
     * @param  array<string, mixed>  $runtime
     * @return array<string, mixed>
     */
    private function applyProviderContext(array $runtime, ?AiProviderProfile $provider): array
    {
        if (! $provider instanceof AiProviderProfile) {
            return $runtime;
        }

        $runtime['provider'] = $provider->provider;
        $runtime['model'] = $provider->defaultModel !== '' ? $provider->defaultModel : null;
        $runtime['reasoning_effort'] = $provider->defaultReasoningEffort ?: null;

        return $runtime;
    }

    /**
     * @return array{key:string,label:string,status:string,at:string,message:?string}
     */
    private function event(string $key, string $label, string $status, ?string $message = null): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'status' => $status,
            'at' => $this->timestamp(),
            'message' => $message,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function normalizeTimeline(mixed $timeline): array
    {
        return is_array($timeline) ? array_values(array_filter($timeline, 'is_array')) : [];
    }

    private function timestamp(): string
    {
        return now()->toIso8601String();
    }
}
