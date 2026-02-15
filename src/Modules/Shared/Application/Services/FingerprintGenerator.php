<?php

declare(strict_types=1);

namespace Modules\Shared\Application\Services;

use Modules\Shared\Domain\DTO\RawNewsData;

final class FingerprintGenerator
{
    public function generate(int $sourceId, string $link, string $publishedAt): string
    {
        return hash('sha256', $sourceId.'|'.$link.'|'.$publishedAt);
    }

    public function attachFingerprint(RawNewsData $raw): RawNewsData
    {
        return new RawNewsData(
            sourceId: $raw->sourceId,
            externalId: $raw->externalId,
            title: $raw->title,
            link: $raw->link,
            content: $raw->content,
            publishedAt: $raw->publishedAt,
            language: $raw->language,
            metadata: $raw->metadata,
            imageUrl: $raw->imageUrl,
            media: $raw->media,
            fingerprint: $this->generate($raw->sourceId, $raw->link, $raw->publishedAt->toRfc3339String()),
            rawId: $raw->rawId,
        );
    }
}
