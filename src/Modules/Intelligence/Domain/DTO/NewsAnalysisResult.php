<?php

declare(strict_types=1);

namespace Modules\Intelligence\Domain\DTO;

final readonly class NewsAnalysisResult
{
    /**
     * @param  array<int, string>  $tags
     * @param  array<string, mixed>  $analysisMetadata
     */
    public function __construct(
        public string $translatedContent,
        public ?string $generatedTitle,
        public string $category,
        public array $tags,
        public int $sentiment,
        public array $analysisMetadata,
    ) {}
}
