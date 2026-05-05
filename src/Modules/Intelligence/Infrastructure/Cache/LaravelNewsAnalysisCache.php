<?php

declare(strict_types=1);

namespace Modules\Intelligence\Infrastructure\Cache;

use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Modules\Intelligence\Domain\Contracts\NewsAnalysisCache;
use Modules\Intelligence\Domain\DTO\NewsAnalysisResult;

final readonly class LaravelNewsAnalysisCache implements NewsAnalysisCache
{
    public function __construct(private CacheFactory $cache) {}

    public function get(
        string $fingerprint,
        string $provider,
        string $model,
        string $reasoningEffort,
        int $analysisVersion,
    ): ?NewsAnalysisResult {
        $payload = $this->cache->store($this->store())->get($this->key(
            fingerprint: $fingerprint,
            provider: $provider,
            model: $model,
            reasoningEffort: $reasoningEffort,
            analysisVersion: $analysisVersion,
        ));

        return $this->hydrate($payload);
    }

    public function put(
        string $fingerprint,
        string $provider,
        string $model,
        string $reasoningEffort,
        int $analysisVersion,
        NewsAnalysisResult $result,
    ): void {
        $this->cache->store($this->store())->put(
            $this->key(
                fingerprint: $fingerprint,
                provider: $provider,
                model: $model,
                reasoningEffort: $reasoningEffort,
                analysisVersion: $analysisVersion,
            ),
            $this->dehydrate($result),
            $this->ttlSeconds(),
        );
    }

    private function store(): ?string
    {
        $store = config('intelligence.analysis_cache.store', 'redis');

        return is_string($store) && $store !== '' ? $store : null;
    }

    private function ttlSeconds(): int
    {
        return max(60, (int) config('intelligence.analysis_cache.ttl_seconds', 604800));
    }

    private function key(
        string $fingerprint,
        string $provider,
        string $model,
        string $reasoningEffort,
        int $analysisVersion,
    ): string {
        return 'intelligence:news-analysis:'.sha1(json_encode([
            'fingerprint' => $fingerprint,
            'provider' => $provider,
            'model' => $model,
            'reasoning_effort' => $reasoningEffort,
            'analysis_version' => $analysisVersion,
        ], JSON_THROW_ON_ERROR));
    }

    /**
     * @return array<string, mixed>
     */
    private function dehydrate(NewsAnalysisResult $result): array
    {
        return [
            'translated_content' => $result->translatedContent,
            'generated_title' => $result->generatedTitle,
            'category' => $result->category,
            'tags' => $result->tags,
            'sentiment' => $result->sentiment,
        ];
    }

    private function hydrate(mixed $payload): ?NewsAnalysisResult
    {
        if (! is_array($payload)) {
            return null;
        }

        $translatedContent = $payload['translated_content'] ?? null;
        $generatedTitle = $payload['generated_title'] ?? null;
        $category = $payload['category'] ?? null;
        $tags = $payload['tags'] ?? null;
        $sentiment = $payload['sentiment'] ?? null;

        if (! is_string($translatedContent)
            || (! is_string($generatedTitle) && $generatedTitle !== null)
            || ! is_string($category)
            || ! is_array($tags)
            || ! array_is_list($tags)
            || ! is_int($sentiment)
        ) {
            return null;
        }

        $tags = array_values(array_filter($tags, static fn (mixed $tag): bool => is_string($tag)));

        return new NewsAnalysisResult(
            translatedContent: $translatedContent,
            generatedTitle: $generatedTitle,
            category: $category,
            tags: $tags,
            sentiment: $sentiment,
            analysisMetadata: [],
        );
    }
}
