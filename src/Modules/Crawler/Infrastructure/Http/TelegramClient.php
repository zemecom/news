<?php

declare(strict_types=1);

namespace Modules\Crawler\Infrastructure\Http;

use Illuminate\Support\Collection;
use InvalidArgumentException;
use Modules\Crawler\Domain\Contracts\TelegramClient as TelegramClientContract;
use Modules\Crawler\Infrastructure\Services\TelegramParserResolver;
use Saloon\Enums\Method;
use Saloon\Http\Response;

final readonly class TelegramClient implements TelegramClientContract
{
    public function __construct(
        private RssConnector $connector,
        private TelegramParserResolver $resolver
    ) {}

    public function fetch(string $channel, ?\Carbon\Carbon $dateFrom = null, ?\Carbon\Carbon $dateTo = null, ?int $limit = null): Collection
    {
        $channelName = $this->resolveChannelName($channel);
        $baseUrl = sprintf('https://t.me/s/%s', $channelName);
        $this->assertAllowedHost($baseUrl);

        $limit ??= max(1, (int) config('crawler.telegram.max_items', 50));
        $before = null;
        $seen = [];
        $items = [];

        $parser = $this->resolver->resolve($channelName);

        while (count($items) < $limit) {
            $url = $before === null ? $baseUrl : sprintf('%s?before=%d', $baseUrl, $before);
            $response = $this->request($url);

            $pageItems = $parser->parse($response->body(), ['channel' => '@'.$channelName]);

            if ($pageItems->isEmpty()) {
                break;
            }

            $lastPostId = null;
            $newItems = 0;
            /** @var array<string, mixed> $item */
            foreach ($pageItems as $item) {
                // Date filtering
                if (isset($item['pubDate'])) {
                    $pubDate = \Carbon\Carbon::parse($item['pubDate']);
                    if ($dateFrom && $pubDate->lt($dateFrom)) {
                        // Reached messages older than dateFrom, stop fetching
                        break 2;
                    }
                    if ($dateTo && $pubDate->gt($dateTo)) {
                        // Message newer than dateTo, skip
                        continue;
                    }
                }

                $externalId = (string) ($item['guid'] ?? '');
                if ($externalId === '' || isset($seen[$externalId])) {
                    continue;
                }

                $seen[$externalId] = true;
                $items[] = $item;
                $newItems++;

                $postId = $this->extractPostId($externalId);
                if ($postId !== null) {
                    $lastPostId = $lastPostId === null ? $postId : min($lastPostId, $postId);
                }

                if (count($items) >= $limit) {
                    break;
                }
            }

            if ($newItems === 0 || $lastPostId === null || $lastPostId <= 1) {
                break;
            }

            $nextBefore = $lastPostId - 1;
            if ($before !== null && $nextBefore >= $before) {
                break;
            }

            $before = $nextBefore;
        }

        return collect($items);
    }

    private function request(string $url): Response
    {
        return $this->connector->send(
            new class($url) extends \Saloon\Http\Request
            {
                protected Method $method = Method::GET;

                public function __construct(private readonly string $url) {}

                public function resolveEndpoint(): string
                {
                    return $this->url;
                }

                public function defaultHeaders(): array
                {
                    return [
                        'User-Agent' => 'SmartNewsBot/1.0',
                    ];
                }
            }
        );
    }

    private function resolveChannelName(string $channel): string
    {
        $channel = trim($channel);
        if ($channel === '') {
            throw new InvalidArgumentException('Telegram channel is empty.');
        }

        if (str_starts_with($channel, '@')) {
            return $this->normalizeChannelName(substr($channel, 1));
        }

        if (filter_var($channel, FILTER_VALIDATE_URL) !== false) {
            $parts = parse_url($channel);
            $host = (string) ($parts['host'] ?? '');
            $path = trim((string) ($parts['path'] ?? ''), '/');

            if ($host !== '' && str_contains($host, 't.me')) {
                if (str_starts_with($path, 's/')) {
                    $path = substr($path, 2);
                }

                return $this->normalizeChannelName($path);
            }
        }

        return $this->normalizeChannelName($channel);
    }

    private function normalizeChannelName(string $name): string
    {
        $normalized = preg_replace('/[^a-zA-Z0-9_]/', '', $name) ?? '';
        if ($normalized === '') {
            throw new InvalidArgumentException('Invalid telegram channel name.');
        }

        return $normalized;
    }

    private function assertAllowedHost(string $url): void
    {
        $host = parse_url($url, PHP_URL_HOST);
        $allowlist = config('crawler.allowlist', []);

        if ($host === null || $host === '') {
            throw new InvalidArgumentException('Invalid Telegram URL host.');
        }

        if ($allowlist !== [] && ! in_array($host, $allowlist, true)) {
            throw new InvalidArgumentException('Telegram host is not in allowlist.');
        }
    }

    private function extractPostId(string $externalId): ?int
    {
        $parts = explode('/', $externalId);
        if (count($parts) !== 2 || ! ctype_digit($parts[1])) {
            return null;
        }

        return (int) $parts[1];
    }
}
