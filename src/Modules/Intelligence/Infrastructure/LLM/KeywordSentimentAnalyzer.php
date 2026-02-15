<?php

declare(strict_types=1);

namespace Modules\Intelligence\Infrastructure\LLM;

use Modules\Intelligence\Domain\Contracts\SentimentAnalyzer;

final class KeywordSentimentAnalyzer implements SentimentAnalyzer
{
    /** @var array<int, string> */
    private array $positiveWords = [
        'good',
        'great',
        'excellent',
        'growth',
        'success',
        'улучш',
        'рост',
        'успех',
    ];

    /** @var array<int, string> */
    private array $negativeWords = [
        'bad',
        'crisis',
        'drop',
        'loss',
        'decline',
        'паден',
        'кризис',
        'убыт',
    ];

    public function score(string $content): int
    {
        $normalized = mb_strtolower($content);
        $score = 0;

        foreach ($this->positiveWords as $word) {
            if (str_contains($normalized, $word)) {
                $score += 2;
            }
        }

        foreach ($this->negativeWords as $word) {
            if (str_contains($normalized, $word)) {
                $score -= 2;
            }
        }

        return max(-10, min(10, $score));
    }
}
