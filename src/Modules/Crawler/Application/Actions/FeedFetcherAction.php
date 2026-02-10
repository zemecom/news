<?php

declare(strict_types=1);

namespace Modules\Crawler\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Modules\Crawler\Infrastructure\Http\RssClient;
use Modules\Crawler\Infrastructure\Messaging\RawPublisher;
use Modules\Shared\Application\Services\FingerprintGenerator;
use Modules\Shared\Domain\DTO\RawNewsData;

final class FeedFetcherAction
{
    public function __construct(
        private RssClient $client,
        private RawPublisher $publisher,
        private FingerprintGenerator $fingerprintGenerator,
    ) {
    }

    /**
     * @param array{id:int,url:string,language_default:string|null} $source
     */
    public function __invoke(array $source): void
    {
        $items = $this->client->fetch($source['url']);

        $items->each(function (array $item) use ($source): void {
            $raw = new RawNewsData(
                sourceId: $source['id'],
                externalId: $item['guid'] ?? null,
                title: $item['title'] ?? '',
                link: $item['link'] ?? '',
                content: $item['content'] ?? $item['description'] ?? '',
                publishedAt: CarbonImmutable::parse($item['pubDate'] ?? 'now'),
                language: $item['language'] ?? ($source['language_default'] ?? 'en'),
                metadata: [
                    'author' => $item['author'] ?? null,
                    'categories' => $item['categories'] ?? [],
                    'source_url' => $source['url'],
                ],
                fingerprint: '',
                rawId: null,
            );

            $raw = $this->fingerprintGenerator->attachFingerprint($raw);
            $this->publisher->publish($raw);
        });
    }
}
