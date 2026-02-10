<?php

declare(strict_types=1);

namespace Modules\Crawler;

use Illuminate\Support\ServiceProvider;
use Modules\Crawler\Application\Actions\FeedFetcherAction;
use Modules\Crawler\Infrastructure\Http\RssConnector;
use Modules\Crawler\Infrastructure\Messaging\RawPublisher;
use Modules\Shared\Application\Services\FingerprintGenerator;

final class CrawlerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(FeedFetcherAction::class);
        $this->app->singleton(RssConnector::class);
        $this->app->singleton(RawPublisher::class);
        $this->app->singleton(FingerprintGenerator::class);
    }
}
