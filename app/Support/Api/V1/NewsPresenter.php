<?php

declare(strict_types=1);

namespace App\Support\Api\V1;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Modules\Catalog\Infrastructure\Persistence\Models\NewsItem;

final class NewsPresenter
{
    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    public static function publicListItem(array $item): array
    {
        return [
            'id' => (string) ($item['id'] ?? ''),
            'source' => [
                'id' => (int) ($item['source_id'] ?? 0),
                'name' => $item['source_name'] ?? null,
            ],
            'title' => self::title($item),
            'excerpt' => Str::limit((string) self::content($item), 220),
            'sentiment' => (int) ($item['sentiment'] ?? 0),
            'tags' => self::stringList($item['tags'] ?? []),
            'important' => (bool) ($item['important'] ?? false),
            'status' => (string) ($item['status'] ?? ''),
            'published_at' => $item['published_at'] ?? null,
            'media' => self::media($item),
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    public static function publicDetail(array $item): array
    {
        return self::publicListItem($item) + [
            'content' => self::content($item),
            'analysis' => self::analysis(is_array($item['source_metadata'] ?? null) ? $item['source_metadata'] : null),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function adminListItem(NewsItem $item): array
    {
        return [
            'id' => (string) $item->getKey(),
            'source' => [
                'id' => (int) $item->source_id,
                'name' => $item->source?->name,
            ],
            'title' => [
                'original' => $item->title_original,
                'generated' => $item->title_generated,
                'effective' => $item->title_generated ?: $item->title_original,
            ],
            'status' => (string) $item->status,
            'important' => (bool) ($item->is_important ?? false),
            'sentiment' => (int) ($item->sentiment_score ?? 0),
            'analysis' => self::analysis($item->source_metadata),
            'published_at' => $item->published_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function adminDetail(NewsItem $item): array
    {
        return self::adminListItem($item) + [
            'content_original' => $item->content_original,
            'content_translated' => $item->content_translated,
            'raw_fingerprint' => $item->raw_fingerprint,
            'tags' => self::stringList($item->tags),
            'source_metadata' => is_array($item->source_metadata) ? $item->source_metadata : [],
            'media' => $item->media ?? [],
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private static function title(array $item): string
    {
        $generated = $item['title_generated'] ?? null;

        return is_string($generated) && $generated !== ''
            ? $generated
            : (string) ($item['title_original'] ?? '');
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private static function content(array $item): string
    {
        $translated = $item['content_translated'] ?? null;

        return is_string($translated) && $translated !== ''
            ? $translated
            : (string) ($item['content_original'] ?? '');
    }

    /**
     * @param  array<int|string, mixed>|null  $sourceMetadata
     * @return array<string, mixed>
     */
    private static function analysis(?array $sourceMetadata): array
    {
        $metadata = $sourceMetadata ?? [];

        return [
            'provider' => Arr::get($metadata, 'analysis.provider'),
            'model' => Arr::get($metadata, 'analysis.model'),
            'reasoning_effort' => Arr::get($metadata, 'analysis.reasoning_effort'),
            'status' => Arr::get($metadata, 'analysis_runtime.status'),
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private static function media(array $item): array
    {
        return [
            'image_url' => $item['image_url'] ?? null,
            'image_url_original' => $item['image_url_original'] ?? null,
            'image_url_local' => $item['image_url_local'] ?? null,
            'items' => is_array($item['media'] ?? null) ? $item['media'] : [],
            'original_items' => is_array($item['media_original'] ?? null) ? $item['media_original'] : [],
            'local_items' => is_array($item['media_local'] ?? null) ? $item['media_local'] : [],
        ];
    }

    /**
     * @return list<string>
     */
    private static function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, static fn (mixed $item): bool => is_string($item) && $item !== ''));
    }
}
