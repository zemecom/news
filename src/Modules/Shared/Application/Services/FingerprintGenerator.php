<?php

declare(strict_types=1);

namespace Modules\Shared\Application\Services;

use Carbon\CarbonImmutable;
use Modules\Shared\Domain\DTO\RawNewsData;

final class FingerprintGenerator
{
    public function generate(
        int $sourceId,
        string $link,
        string $publishedAt,
        ?string $title = null,
        ?string $externalId = null,
    ): string {
        $minuteBucket = CarbonImmutable::parse($publishedAt)
            ->setSecond(0)
            ->setTimezone('UTC')
            ->toIso8601String();

        $normalizedTitle = $this->normalizeTitle($title ?? '');
        $normalizedExternalId = trim(mb_strtolower((string) $externalId));

        if ($normalizedExternalId !== '') {
            return hash('sha256', sprintf(
                'src:%d|ext:%s',
                $sourceId,
                $normalizedExternalId
            ));
        }

        if ($normalizedTitle !== '') {
            return hash('sha256', sprintf(
                'src:%d|title:%s|time:%s',
                $sourceId,
                $normalizedTitle,
                $minuteBucket
            ));
        }

        return hash('sha256', sprintf(
            'src:%d|link:%s|time:%s',
            $sourceId,
            $this->normalizeLink($link),
            $minuteBucket
        ));
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
            fingerprint: $this->generate(
                sourceId: $raw->sourceId,
                link: $raw->link,
                publishedAt: $raw->publishedAt->toRfc3339String(),
                title: $raw->title,
                externalId: $raw->externalId,
            ),
            rawId: $raw->rawId,
        );
    }

    private function normalizeTitle(string $title): string
    {
        $title = mb_strtolower(trim($title));
        if ($title === '') {
            return '';
        }

        $plain = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $title) ?? '';

        return trim((string) preg_replace('/\s+/u', ' ', $plain));
    }

    private function normalizeLink(string $link): string
    {
        $link = trim($link);
        if ($link === '') {
            return '';
        }

        $parts = parse_url($link);
        if (! is_array($parts)) {
            return mb_strtolower($link);
        }

        $host = isset($parts['host']) ? mb_strtolower($parts['host']) : '';
        $path = isset($parts['path']) ? rtrim($parts['path'], '/') : '';

        return $host.$path;
    }
}
