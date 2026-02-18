<?php

declare(strict_types=1);

namespace Modules\Delivery;

use Illuminate\Support\ServiceProvider;
use Modules\Delivery\Domain\Contracts\NewsFeedReader;
use Modules\Delivery\Domain\Contracts\SourceAdminReader;
use Modules\Delivery\Domain\Contracts\SourcePublicReader;
use Modules\Delivery\Infrastructure\Persistence\EloquentNewsFeedReader;
use Modules\Delivery\Infrastructure\Persistence\EloquentSourceAdminReader;
use Modules\Delivery\Infrastructure\Persistence\EloquentSourcePublicReader;
use Override;

final class DeliveryServiceProvider extends ServiceProvider
{
    #[Override]
    public function register(): void
    {
        $this->app->bind(NewsFeedReader::class, EloquentNewsFeedReader::class);
        $this->app->bind(SourceAdminReader::class, EloquentSourceAdminReader::class);
        $this->app->bind(SourcePublicReader::class, EloquentSourcePublicReader::class);
    }
}
