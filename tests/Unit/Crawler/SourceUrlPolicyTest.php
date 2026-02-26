<?php

declare(strict_types=1);

namespace Tests\Unit\Crawler;

use InvalidArgumentException;
use Modules\Crawler\Infrastructure\Security\SourceUrlPolicy;
use Tests\TestCase;

final class SourceUrlPolicyTest extends TestCase
{
    public function test_it_allows_wildcard_host_for_rss(): void
    {
        config()->set('crawler.allowlist', [
            'global' => [],
            'rss' => ['*.example.com'],
            'telegram' => ['t.me'],
        ]);
        config()->set('crawler.security.allowed_source_schemes', ['https']);
        config()->set('crawler.security.deny_private_hosts', true);

        $policy = new SourceUrlPolicy;

        $policy->assertAllowedForRss('https://news.example.com/feed.xml');

        $this->addToAssertionCount(1);
    }

    public function test_it_rejects_non_allowlisted_rss_host(): void
    {
        config()->set('crawler.allowlist', [
            'global' => [],
            'rss' => ['rss.allowed.dev'],
            'telegram' => ['t.me'],
        ]);
        config()->set('crawler.security.allowed_source_schemes', ['https']);
        config()->set('crawler.security.deny_private_hosts', true);

        $policy = new SourceUrlPolicy;

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('RSS host "example.com" is not in allowlist.');

        $policy->assertAllowedForRss('https://example.com/feed.xml');
    }

    public function test_it_rejects_private_or_local_hosts_when_hardening_enabled(): void
    {
        config()->set('crawler.allowlist', []);
        config()->set('crawler.security.allowed_source_schemes', ['https']);
        config()->set('crawler.security.deny_private_hosts', true);

        $policy = new SourceUrlPolicy;

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('RSS host "127.0.0.1" is not public.');

        $policy->assertAllowedForRss('https://127.0.0.1/feed.xml');
    }
}
