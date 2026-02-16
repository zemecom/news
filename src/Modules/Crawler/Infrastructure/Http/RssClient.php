<?php

declare(strict_types=1);

namespace Modules\Crawler\Infrastructure\Http;

use Illuminate\Support\Collection;
use Modules\Crawler\Domain\Contracts\RssClient as RssClientContract;
use Modules\Crawler\Infrastructure\Services\RssParserResolver;
use Saloon\Enums\Method;

final class RssClient implements RssClientContract
{
    public function __construct(
        private RssConnector $connector,
        private RssParserResolver $resolver
    ) {}

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

        $parser = $this->resolver->resolve($url);

        return $parser->parse($response->body());
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
}
