<?php

declare(strict_types=1);

namespace Modules\Crawler\Infrastructure\Http;

use Illuminate\Support\Collection;
use Saloon\Http\Response;

final class RssClient
{
    public function __construct(private RssConnector $connector)
    {
    }

    public function fetch(string $url): Collection
    {
        $this->assertAllowedHost($url);
        $response = $this->connector->send(
            new class($url) extends \Saloon\Http\Request {
                protected string $method = 'GET';
                public function __construct(private string $url)
                {
                }
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

        return $this->mapToItems($response);
    }

    private function assertAllowedHost(string $url): void
    {
        $host = parse_url($url, PHP_URL_HOST);
        $allowlist = config('crawler.allowlist', []);

        if ($host === null || $host === '') {
            throw new \InvalidArgumentException('Invalid RSS URL host.');
        }

        if ($allowlist !== [] && ! in_array($host, $allowlist, true)) {
            throw new \InvalidArgumentException('RSS host is not in allowlist.');
        }
    }

    private function mapToItems(Response $response): Collection
    {
        // Простая обёртка: парсинг RSS/Atom можно заменить на более надёжный парсер
        $body = $response->body();
        $xml = @simplexml_load_string($body, 'SimpleXMLElement', LIBXML_NOCDATA);
        $items = [];
        if ($xml && isset($xml->channel->item)) {
            foreach ($xml->channel->item as $item) {
                $items[] = [
                    'title' => (string) ($item->title ?? ''),
                    'link' => (string) ($item->link ?? ''),
                    'description' => (string) ($item->description ?? ''),
                    'content' => (string) ($item->{'content:encoded'} ?? ''),
                    'pubDate' => (string) ($item->pubDate ?? ''),
                    'guid' => (string) ($item->guid ?? ''),
                    'language' => (string) ($item->language ?? ''),
                    'categories' => array_map('strval', iterator_to_array($item->category ?? [])),
                    'author' => (string) ($item->author ?? ''),
                ];
            }
        }

        return collect($items);
    }
}
