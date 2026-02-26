<?php

declare(strict_types=1);

namespace Modules\Catalog;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Modules\Catalog\Application\Listeners\QueueMediaPreloadListener;
use Modules\Catalog\Application\Listeners\UpdateSourceStatusListener;
use Modules\Catalog\Domain\Contracts\NewsMediaAssetRepository;
use Modules\Catalog\Domain\Contracts\NewsRepository;
use Modules\Catalog\Domain\Contracts\SourceRepository;
use Modules\Catalog\Infrastructure\Persistence\EloquentNewsMediaAssetRepository;
use Modules\Catalog\Infrastructure\Persistence\EloquentNewsRepository;
use Modules\Catalog\Infrastructure\Persistence\EloquentSourceRepository;
use Modules\Shared\Domain\Contracts\NewsStore;
use Modules\Shared\Domain\Events\NewsEnriched;
use Modules\Shared\Domain\Events\SourceFetchFailed;
use Modules\Shared\Domain\Events\SourceFetchSucceeded;
use Override;

final class CatalogServiceProvider extends ServiceProvider
{
    #[Override]
    public function register(): void
    {
        $this->app->singleton(NewsRepository::class, EloquentNewsRepository::class);
        $this->app->singleton(NewsStore::class, EloquentNewsRepository::class);
        $this->app->singleton(NewsMediaAssetRepository::class, EloquentNewsMediaAssetRepository::class);
        $this->app->singleton(SourceRepository::class, EloquentSourceRepository::class);
    }

    public function boot(): void
    {
        Event::listen(
            [SourceFetchSucceeded::class, SourceFetchFailed::class],
            UpdateSourceStatusListener::class
        );
        Event::listen(NewsEnriched::class, QueueMediaPreloadListener::class);
    }
}
