<?php

declare(strict_types=1);

namespace Tests\Unit\Shared;

use Modules\Shared\Application\Services\FingerprintGenerator;
use PHPUnit\Framework\TestCase;

final class FingerprintGeneratorTest extends TestCase
{
    public function test_uses_external_id_as_primary_dedup_key(): void
    {
        $generator = new FingerprintGenerator;

        $first = $generator->generate(
            sourceId: 11,
            link: 'https://t.me/toporlive/100',
            publishedAt: '2026-02-15T10:15:22+00:00',
            title: 'Post A',
            externalId: 'toporlive/100',
        );
        $second = $generator->generate(
            sourceId: 11,
            link: 'https://t.me/toporlive/100?single',
            publishedAt: '2026-02-15T10:15:59+00:00',
            title: 'Post A copy',
            externalId: 'TOPORLIVE/100',
        );

        $this->assertSame($first, $second);
    }

    public function test_uses_title_and_minute_for_same_source_without_external_id(): void
    {
        $generator = new FingerprintGenerator;

        $first = $generator->generate(
            sourceId: 2,
            link: 'https://example.com/news-1',
            publishedAt: '2026-02-15T12:01:05+00:00',
            title: 'ЦБ повысил ставку',
        );
        $second = $generator->generate(
            sourceId: 2,
            link: 'https://example.com/news-1?utm_source=feed',
            publishedAt: '2026-02-15T12:01:59+00:00',
            title: 'ЦБ повысил ставку!!!',
        );
        $third = $generator->generate(
            sourceId: 2,
            link: 'https://example.com/news-1',
            publishedAt: '2026-02-15T12:02:01+00:00',
            title: 'ЦБ повысил ставку',
        );

        $this->assertSame($first, $second);
        $this->assertNotSame($first, $third);
    }
}
