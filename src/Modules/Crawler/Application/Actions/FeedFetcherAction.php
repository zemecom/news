<?php

declare(strict_types=1);

namespace Modules\Crawler\Application\Actions;

use Modules\Crawler\Application\Services\RawNewsFactory;
use Modules\Crawler\Domain\Contracts\RawPublisher;
use Modules\Crawler\Domain\Contracts\RssClient;

final class FeedFetcherAction
{
    public function __construct(
        private RssClient $client,
        private RawPublisher $publisher,
        private RawNewsFactory $rawNewsFactory,
    ) {}

    /**
     * @param  array{id:int,url:string,language_default:string|null}  $source
     */
    public function __invoke(array $source): void
    {
        $items = $this->client->fetch($source['url']);

        $items->each(function (array $item) use ($source): void {
            $raw = $this->rawNewsFactory->fromRss($source, $item);
            $this->publisher->publish($raw);
        });
    }
}
