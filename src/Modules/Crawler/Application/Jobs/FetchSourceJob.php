<?php

declare(strict_types=1);

namespace Modules\Crawler\Application\Jobs;

use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Crawler\Application\Actions\FeedFetcherAction;

final class FetchSourceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @param  array{id:int,url:string,type:string,language_default:string|null}  $source
     */
    public function __construct(
        public readonly array $source,
        public readonly ?Carbon $dateFrom = null,
        public readonly ?Carbon $dateTo = null,
        public readonly ?int $limit = null,
    ) {
        $this->onQueue('crawler_tasks');
    }

    public function handle(FeedFetcherAction $fetchFeed): void
    {
        $fetchFeed(
            source: $this->source,
            dateFrom: $this->dateFrom,
            dateTo: $this->dateTo,
            limit: $this->limit
        );
    }
}
