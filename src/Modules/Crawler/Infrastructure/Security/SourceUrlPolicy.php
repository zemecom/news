<?php

declare(strict_types=1);

namespace Modules\Crawler\Infrastructure\Security;

use InvalidArgumentException;

final class SourceUrlPolicy
{
    public function assertAllowedForRss(string $url): void
    {
        $this->assertAllowed($url, 'rss');
    }

    public function assertAllowedForTelegram(string $url): void
    {
        $this->assertAllowed($url, 'telegram');
    }

    private function assertAllowed(string $url, string $sourceType): void
    {
        $host = $this->extractHost($url);
        $scheme = $this->extractScheme($url);

        $this->assertAllowedScheme($scheme, $sourceType);
        $this->assertAllowedHost($host, $sourceType);
        $this->assertHostIsPublic($host, $sourceType);
    }

    private function extractHost(string $url): string
    {
        $host = parse_url($url, PHP_URL_HOST);
        if (! is_string($host) || trim($host) === '') {
            throw new InvalidArgumentException('Invalid source URL host.');
        }

        return mb_strtolower(trim($host));
    }

    private function extractScheme(string $url): string
    {
        $scheme = parse_url($url, PHP_URL_SCHEME);
        if (! is_string($scheme) || trim($scheme) === '') {
            throw new InvalidArgumentException('Invalid source URL scheme.');
        }

        return mb_strtolower(trim($scheme));
    }

    private function assertAllowedScheme(string $scheme, string $sourceType): void
    {
        $allowedSchemes = config('crawler.security.allowed_source_schemes', ['https']);
        if (! is_array($allowedSchemes) || $allowedSchemes === []) {
            $allowedSchemes = ['https'];
        }

        $normalized = [];
        foreach ($allowedSchemes as $allowedScheme) {
            if (is_string($allowedScheme) && trim($allowedScheme) !== '') {
                $normalized[] = mb_strtolower(trim($allowedScheme));
            }
        }

        if ($normalized !== [] && ! in_array($scheme, $normalized, true)) {
            throw new InvalidArgumentException(sprintf(
                '%s source scheme "%s" is not allowed.',
                mb_strtoupper($sourceType),
                $scheme
            ));
        }
    }

    private function assertAllowedHost(string $host, string $sourceType): void
    {
        $allowlist = $this->resolveAllowlist($sourceType);
        if ($allowlist === []) {
            return;
        }

        foreach ($allowlist as $pattern) {
            if ($this->matchesPattern($host, $pattern)) {
                return;
            }
        }

        throw new InvalidArgumentException(sprintf(
            '%s host "%s" is not in allowlist.',
            mb_strtoupper($sourceType),
            $host
        ));
    }

    private function assertHostIsPublic(string $host, string $sourceType): void
    {
        if ((bool) config('crawler.security.deny_private_hosts', true) !== true) {
            return;
        }

        if ($host === 'localhost'
            || str_ends_with($host, '.local')
            || str_ends_with($host, '.internal')) {
            throw new InvalidArgumentException(sprintf(
                '%s host "%s" is not allowed.',
                mb_strtoupper($sourceType),
                $host
            ));
        }

        $isIp = filter_var($host, FILTER_VALIDATE_IP) !== false;
        if ($isIp) {
            $isPublicIp = filter_var(
                $host,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
            ) !== false;

            if (! $isPublicIp) {
                throw new InvalidArgumentException(sprintf(
                    '%s host "%s" is not public.',
                    mb_strtoupper($sourceType),
                    $host
                ));
            }
        }
    }

    /**
     * @return array<int, string>
     */
    private function resolveAllowlist(string $sourceType): array
    {
        $raw = config('crawler.allowlist', []);
        if (! is_array($raw)) {
            return [];
        }

        $entries = [];
        if (array_is_list($raw)) {
            $entries = $raw;
        } else {
            $global = $raw['global'] ?? [];
            $typeSpecific = $raw[$sourceType] ?? [];
            if (is_array($global)) {
                $entries = [...$entries, ...$global];
            }
            if (is_array($typeSpecific)) {
                $entries = [...$entries, ...$typeSpecific];
            }
        }

        $normalized = [];
        foreach ($entries as $entry) {
            if (! is_string($entry)) {
                continue;
            }

            $value = mb_strtolower(trim($entry));
            if ($value !== '') {
                $normalized[$value] = true;
            }
        }

        return array_keys($normalized);
    }

    private function matchesPattern(string $host, string $pattern): bool
    {
        if ($pattern === $host) {
            return true;
        }

        if (! str_starts_with($pattern, '*.')) {
            return false;
        }

        $suffix = substr($pattern, 1);
        if ($suffix === '') {
            return false;
        }

        return str_ends_with($host, $suffix);
    }
}
