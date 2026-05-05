<?php

declare(strict_types=1);

namespace Modules\Intelligence\Infrastructure\Codex;

use Modules\Shared\Domain\DTO\RawNewsData;

final readonly class CodexNewsAnalysisContract
{
    public const int ANALYSIS_VERSION = 2;

    /**
     * @param  list<string>  $categories
     */
    public function __construct(private array $categories) {}

    /**
     * @param  array<int, mixed>  $categories
     */
    public static function fromConfig(array $categories): self
    {
        return new self(array_values(array_filter(
            $categories,
            static fn (mixed $category): bool => is_string($category) && $category !== '',
        )));
    }

    /**
     * @return list<string>
     */
    public function categories(): array
    {
        return $this->categories;
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(): array
    {
        return [
            '$schema' => 'http://json-schema.org/draft-07/schema#',
            'type' => 'object',
            'additionalProperties' => false,
            'required' => [
                'translated_content',
                'generated_title',
                'category',
                'tags',
                'sentiment',
            ],
            'properties' => [
                'translated_content' => [
                    'type' => 'string',
                ],
                'generated_title' => [
                    'type' => ['string', 'null'],
                    'maxLength' => 140,
                ],
                'category' => [
                    'type' => 'string',
                    'enum' => $this->categories,
                ],
                'tags' => [
                    'type' => 'array',
                    'maxItems' => 8,
                    'items' => [
                        'type' => 'string',
                        'maxLength' => 32,
                    ],
                ],
                'sentiment' => [
                    'type' => 'integer',
                    'minimum' => -10,
                    'maximum' => 10,
                ],
            ],
        ];
    }

    public function prompt(RawNewsData $raw): string
    {
        $categories = implode(', ', $this->categories);
        $language = $raw->language !== '' ? $raw->language : 'en';
        $version = self::ANALYSIS_VERSION;

        return <<<PROMPT
You are a news analysis engine.
Return only JSON matching the provided schema.
Contract version: {$version}.

Tasks:
1. Translate the news content to Russian if it is not already Russian.
2. Produce an objective, non-clickbait title in Russian.
3. Pick exactly one category from this set: {$categories}.
4. Produce 0-8 short lowercase tags.
5. Produce a sentiment score from -10 to 10.

Rules:
- Preserve factual meaning.
- Avoid exaggeration.
- If the original title is already neutral, you may keep its meaning but rewrite it in clean Russian.
- Tags should be concise and lowercase.
- If the content is already in Russian, translated_content may stay semantically identical.

Source language: {$language}
Title: {$raw->title}
Content:
{$raw->content}
PROMPT;
    }
}
