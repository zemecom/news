<?php

declare(strict_types=1);

namespace Modules\Crawler\Application\Services;

use Carbon\CarbonImmutable;
use Modules\Shared\Application\Services\FingerprintGenerator;
use Modules\Shared\Domain\DTO\RawNewsData;
use Throwable;

final readonly class RawNewsFactory
{
    public function __construct(
        private FingerprintGenerator $fingerprintGenerator,
        private IncomingContentSanitizer $sanitizer,
    ) {}

    /**
     * @param  array{id:int,url:string,language_default:?string}  $source
     * @param  array<string, mixed>  $item
     */
    public function fromRss(array $source, array $item): RawNewsData
    {
        $title = $this->sanitizer->sanitizeText((string) ($item['title'] ?? ''));
        $content = $this->sanitizer->sanitizeText((string) ($item['content'] ?? $item['description'] ?? ''));
        $link = $this->sanitizer->sanitizeUrl($this->normalizeString($item['link'] ?? null)) ?? '';
        $author = $this->normalizeAuthor($item['author'] ?? null);
        $categories = $this->sanitizer->sanitizeCategories($item['categories'] ?? []);
        $links = $this->sanitizer->sanitizeLinks($item['links'] ?? []);
        $imageUrl = $this->sanitizer->sanitizeUrl($this->normalizeString($item['image_url'] ?? null));
        $media = $this->sanitizer->sanitizeMedia($item['media'] ?? []);

        $raw = new RawNewsData(
            sourceId: $source['id'],
            externalId: $this->normalizeString($item['guid'] ?? null),
            title: $title,
            link: $link,
            content: $content,
            publishedAt: $this->parsePublishedAt($item['pubDate'] ?? null),
            language: (string) ($item['language'] ?? ($source['language_default'] ?? 'en')),
            metadata: [
                'author' => $author,
                'categories' => $categories,
                'source_url' => $source['url'],
                'links' => $links,
            ],
            imageUrl: $imageUrl,
            media: $media,
            fingerprint: '',
        );

        return $this->fingerprintGenerator->attachFingerprint($raw);
    }

    private function parsePublishedAt(mixed $value): CarbonImmutable
    {
        if (! is_scalar($value)) {
            return CarbonImmutable::now();
        }

        try {
            return CarbonImmutable::parse((string) $value);
        } catch (Throwable) {
            return CarbonImmutable::now();
        }
    }

    private function normalizeString(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized === '' ? null : $normalized;
    }

    private function normalizeAuthor(mixed $author): ?string
    {
        $normalized = $this->normalizeString($author);
        if ($normalized === null) {
            return null;
        }

        $clean = $this->sanitizer->sanitizeText($normalized);

        return $clean === '' ? null : $clean;
    }
}
