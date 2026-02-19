<?php

declare(strict_types=1);

namespace Modules\Catalog;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Modules\Catalog\Application\Listeners\UpdateSourceStatusListener;
use Modules\Catalog\Domain\Contracts\NewsRepository;
use Modules\Catalog\Infrastructure\Persistence\EloquentNewsRepository;
use Modules\Crawler\Domain\Events\SourceFetchFailed;
use Modules\Crawler\Domain\Events\SourceFetchSucceeded;
use Override;

final class CatalogServiceProvider extends ServiceProvider
{
    #[Override]
    public function register(): void
    {
        $this->app->singleton(NewsRepository::class, EloquentNewsRepository::class);
        $this->app->singleton(
            \Modules\Catalog\Domain\Contracts\SourceRepository::class,
            \Modules\Catalog\Infrastructure\Persistence\EloquentSourceRepository::class
        );
    }

    public function boot(): void
    {
        Event::listen(
            [SourceFetchSucceeded::class, SourceFetchFailed::class],
            UpdateSourceStatusListener::class
        );
    }
}
