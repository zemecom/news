<?php

declare(strict_types=1);

namespace Modules\Crawler\Application\Services;

use Carbon\CarbonImmutable;
use Modules\Shared\Application\Services\FingerprintGenerator;
use Modules\Shared\Domain\DTO\RawNewsData;

final class RawNewsFactory
{
    public function __construct(private FingerprintGenerator $fingerprintGenerator) {}

    /**
     * @param  array{id:int,url:string,language_default:?string}  $source
     * @param  array<string, mixed>  $item
     */
    public function fromRss(array $source, array $item): RawNewsData
    {
        $raw = new RawNewsData(
            sourceId: $source['id'],
            externalId: $item['guid'] ?? null,
            title: (string) ($item['title'] ?? ''),
            link: (string) ($item['link'] ?? ''),
            content: (string) ($item['content'] ?? $item['description'] ?? ''),
            publishedAt: CarbonImmutable::parse($item['pubDate'] ?? 'now'),
            language: (string) ($item['language'] ?? ($source['language_default'] ?? 'en')),
            metadata: [
                'author' => $item['author'] ?? null,
                'categories' => $item['categories'] ?? [],
                'source_url' => $source['url'],
            ],
            imageUrl: $item['image_url'] ?? null,
            media: $item['media'] ?? [],
            fingerprint: '',
            rawId: null,
        );

        return $this->fingerprintGenerator->attachFingerprint($raw);
    }
}
