<?php

declare(strict_types=1);

namespace Modules\Crawler\Infrastructure\Http;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Modules\Crawler\Domain\Contracts\TelegramClient as TelegramClientContract;
use Modules\Crawler\Infrastructure\Security\SourceUrlPolicy;
use Modules\Crawler\Infrastructure\Services\TelegramParserResolver;
use Saloon\Enums\Method;
use Saloon\Http\Response;

final readonly class TelegramClient implements TelegramClientContract
{
    public function __construct(
        private RssConnector $connector,
        private TelegramParserResolver $resolver,
        private SourceUrlPolicy $sourceUrlPolicy,
    ) {}

    public function fetch(string $channel, ?\Carbon\Carbon $dateFrom = null, ?\Carbon\Carbon $dateTo = null, ?int $limit = null): Collection
    {
        $channelName = $this->resolveChannelName($channel);
        $baseUrl = sprintf('https://t.me/s/%s', $channelName);
        $this->sourceUrlPolicy->assertAllowedForTelegram($baseUrl);

        $limit ??= 50; // Provide a minimal hardcode fallback just in case, though Command ensures it's set.
        $before = null;
        $seen = [];
        $items = [];

        $parser = $this->resolver->resolve($channelName);
        $logger = Log::channel('stderr');
        $logger->info(sprintf('[Telegram] Starting fetch for channel \'@%s\' (Target limit: %d items)', $channelName, $limit));

        while (count($items) < $limit) {
            $isAjax = $before !== null;
            $url = $before === null ? $baseUrl : sprintf('%s?before=%d', $baseUrl, $before);

            if ($isAjax) {
                // Sleep randomly between requests to prevent IP bans
                $sleepTime = random_int(1, 4);
                $logger->info(sprintf('[Telegram] Sleeping %d seconds before next request...', $sleepTime));
                sleep($sleepTime);
            }

            $logger->info(sprintf('[Telegram] Requesting %s ...', $url));
            $response = $this->request($url, $isAjax);
            $htmlBody = $response->body();

            if ($isAjax) {
                $decoded = json_decode($htmlBody, true);
                if (is_string($decoded)) {
                    $htmlBody = $decoded;
                }
            }

            $pageItems = $parser->parse($htmlBody, ['channel' => '@'.$channelName]);
            $logger->info(sprintf('[Telegram] Parsed %d items from page %s. Extracted total so far: %d', $pageItems->count(), $url, count($items)));

            if ($pageItems->isEmpty()) {
                $logger->info('[Telegram] No more items found on page. Ending pagination.');
                break;
            }

            $lastPostId = null;
            $lastPageItemDate = null;
            /** @var array<string, mixed> $item */
            foreach ($pageItems as $item) {
                // Date filtering
                if (isset($item['pubDate'])) {
                    $pubDate = \Carbon\Carbon::parse($item['pubDate'])->setTimezone('UTC');
                    $lastPageItemDate = $pubDate;

                    $from = $dateFrom ? $dateFrom->copy()->setTimezone('UTC') : null;
                    $to = $dateTo ? $dateTo->copy()->setTimezone('UTC') : null;

                    if ($from && $pubDate->lt($from)) {
                        // Skip this specific item if it's too old
                        continue;
                    }
                    if ($to && $pubDate->gt($to)) {
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

                $postId = $this->extractPostId($externalId);
                if ($postId !== null) {
                    $lastPostId = $lastPostId === null ? $postId : min($lastPostId, $postId);
                }

                if (count($items) >= $limit) {
                    break 2;
                }
            }

            // Telegram 's/' page: oldest items at top, newest at bottom.
            // If the newest item on this page is already older than dateFrom,
            // then all items on older pages (?before=...) will definitely be older than dateFrom.
            if ($dateFrom && $lastPageItemDate) {
                $from = $dateFrom->copy()->setTimezone('UTC');
                if ($lastPageItemDate->lt($from)) {
                    break;
                }
            }

            if ($lastPostId === null || $lastPostId <= 1) {
                break;
            }

            $nextBefore = $lastPostId - 1;
            if ($before !== null && $nextBefore >= $before) {
                $logger->info('[Telegram] Pagination loop detected (nextBefore >= before). Ending.');
                break;
            }

            $before = $nextBefore;
        }

        $logger->info(sprintf('[Telegram] Fetch finished. Total fully extracted items: %d', count($items)));

        return collect($items);
    }

    private function request(string $url, bool $isAjax = false): Response
    {
        return $this->connector->send(
            new class($url, $isAjax) extends \Saloon\Http\Request
            {
                protected Method $method = Method::GET;

                public function __construct(private readonly string $url, private readonly bool $isAjax) {}

                public function resolveEndpoint(): string
                {
                    return $this->url;
                }

                public function defaultHeaders(): array
                {
                    $headers = [
                        'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                        'Accept' => '*/*',
                        'Accept-Language' => 'en-US,en;q=0.9,ru;q=0.8',
                    ];

                    if ($this->isAjax) {
                        $headers['X-Requested-With'] = 'XMLHttpRequest';
                    }

                    return $headers;
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

    private function extractPostId(string $externalId): ?int
    {
        $parts = explode('/', $externalId);
        if (count($parts) !== 2 || ! ctype_digit($parts[1])) {
            return null;
        }

        return (int) $parts[1];
    }
}
