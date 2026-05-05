<?php

declare(strict_types=1);

namespace Modules\Intelligence\Infrastructure\Codex;

final readonly class CodexNewsAnalysisPayload
{
    /**
     * @param  list<string>  $tags
     */
    private function __construct(
        public string $translatedContent,
        public ?string $generatedTitle,
        public string $category,
        public array $tags,
        public int $sentiment,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload, CodexNewsAnalysisContract $contract): self
    {
        self::assertRequiredFields($payload);

        $translatedContent = $payload['translated_content'];
        if (! is_string($translatedContent)) {
            self::fail('translated_content must be a string.');
        }

        $generatedTitle = $payload['generated_title'];
        if (! is_string($generatedTitle) && $generatedTitle !== null) {
            self::fail('generated_title must be a string or null.');
        }

        if (is_string($generatedTitle) && mb_strlen($generatedTitle) > 140) {
            self::fail('generated_title must not exceed 140 characters.');
        }

        $category = $payload['category'];
        if (! is_string($category) || ! in_array($category, $contract->categories(), true)) {
            self::fail('category must be one of the configured categories.');
        }

        $tags = self::validateTags($payload['tags']);

        $sentiment = $payload['sentiment'];
        if (! is_int($sentiment) || $sentiment < -10 || $sentiment > 10) {
            self::fail('sentiment must be an integer between -10 and 10.');
        }

        return new self(
            translatedContent: $translatedContent,
            generatedTitle: $generatedTitle,
            category: $category,
            tags: $tags,
            sentiment: $sentiment,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function assertRequiredFields(array $payload): void
    {
        foreach (['translated_content', 'generated_title', 'category', 'tags', 'sentiment'] as $field) {
            if (! array_key_exists($field, $payload)) {
                self::fail(sprintf('%s is required.', $field));
            }
        }
    }

    /**
     * @return list<string>
     */
    private static function validateTags(mixed $tags): array
    {
        if (! is_array($tags) || ! array_is_list($tags)) {
            self::fail('tags must be a list of strings.');
        }

        if (count($tags) > 8) {
            self::fail('tags must contain no more than 8 items.');
        }

        foreach ($tags as $tag) {
            if (! is_string($tag) || trim($tag) === '') {
                self::fail('tags must contain only non-empty strings.');
            }

            if (mb_strlen($tag) > 32) {
                self::fail('tags must not exceed 32 characters.');
            }
        }

        /** @var list<string> $tags */
        return $tags;
    }

    private static function fail(string $message): never
    {
        throw new CodexException('Invalid Codex analysis payload: '.$message);
    }
}
