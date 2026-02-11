<?php

declare(strict_types=1);

namespace Modules\Crawler\Infrastructure\Http;

use Illuminate\Support\Collection;
use Modules\Crawler\Domain\Contracts\RssClient as RssClientContract;
use Saloon\Enums\Method;
use Saloon\Http\Response;

final class RssClient implements RssClientContract
{
    public function __construct(private RssConnector $connector) {}

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function fetch(string $url): Collection
    {
        $this->assertAllowedHost($url);
        $response = $this->connector->send(
            new class($url) extends \Saloon\Http\Request
            {
                protected Method $method = Method::GET;

                public function __construct(private string $url) {}

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

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function mapToItems(Response $response): Collection
    {
        // Простая обёртка: парсинг RSS/Atom можно заменить на более надёжный парсер
        $body = $response->body();
        $xml = @simplexml_load_string($body, 'SimpleXMLElement', LIBXML_NOCDATA);
        $items = [];
        if ($xml && isset($xml->channel->item)) {
            foreach ($xml->channel->item as $item) {
                $media = [];
                $imageUrl = null;

                // RSS enclosure
                if (isset($item->enclosure)) {
                    foreach ($item->enclosure as $enclosure) {
                        $url = (string) ($enclosure['url'] ?? '');
                        $type = (string) ($enclosure['type'] ?? '');
                        if ($url !== '') {
                            $media[] = ['url' => $url, 'type' => $type ?: null];
                            if ($imageUrl === null && str_starts_with($type, 'image/')) {
                                $imageUrl = $url;
                            }
                        }
                    }
                }

                // media:content
                if (isset($item->{'media:content'})) {
                    foreach ($item->{'media:content'} as $mc) {
                        $url = (string) ($mc['url'] ?? '');
                        $type = (string) ($mc['type'] ?? '');
                        if ($url !== '') {
                            $media[] = ['url' => $url, 'type' => $type ?: null];
                            if ($imageUrl === null && str_starts_with($type, 'image/')) {
                                $imageUrl = $url;
                            }
                        }
                    }
                }

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
                    'image_url' => $imageUrl,
                    'media' => $media,
                ];
            }
        }

        return collect($items);
    }
}
