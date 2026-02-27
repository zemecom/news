<?php

declare(strict_types=1);

namespace Modules\Crawler\Infrastructure\Http;

use Illuminate\Support\Collection;
use Modules\Crawler\Domain\Contracts\RssClient as RssClientContract;
use Modules\Crawler\Infrastructure\Security\SourceUrlPolicy;
use Modules\Crawler\Infrastructure\Services\RssParserResolver;
use Saloon\Enums\Method;

final readonly class RssClient implements RssClientContract
{
    public function __construct(
        private RssConnector $connector,
        private RssParserResolver $resolver,
        private SourceUrlPolicy $sourceUrlPolicy,
    ) {}

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function fetch(string $url, ?\Carbon\Carbon $dateFrom = null, ?\Carbon\Carbon $dateTo = null, ?int $limit = null): Collection
    {
        $this->sourceUrlPolicy->assertAllowedForRss($url);
        $response = $this->connector->send(
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
                        'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36',
                    ];
                }

                public function defaultConfig(): array
                {
                    return [
                        'timeout' => 60,
                        'connect_timeout' => 30,
                    ];
                }
            }
        );

        $parser = $this->resolver->resolve($url);
        $items = $parser->parse($response->body());

        if ($dateFrom || $dateTo) {
            $items = $items->filter(function ($item) use ($dateFrom, $dateTo) {
                if (! isset($item['pubDate'])) {
                    return true;
                }

                $pubDate = \Carbon\Carbon::parse($item['pubDate'])->setTimezone('UTC');
                $from = $dateFrom instanceof \Carbon\Carbon ? $dateFrom->copy()->setTimezone('UTC') : null;
                $to = $dateTo instanceof \Carbon\Carbon ? $dateTo->copy()->setTimezone('UTC') : null;

                if ($from && $pubDate->lt($from)) {
                    return false;
                }

                return ! ($to && $pubDate->gt($to));
            });
        }

        if ($limit) {
            $items = $items->take($limit);
        }

        return $items;
    }
}
