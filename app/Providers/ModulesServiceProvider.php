<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Crawler\CrawlerServiceProvider;
use Modules\Intelligence\IntelligenceServiceProvider;
use Modules\Catalog\CatalogServiceProvider;
use Modules\Delivery\DeliveryServiceProvider;

final class ModulesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->register(CrawlerServiceProvider::class);
        $this->app->register(IntelligenceServiceProvider::class);
        $this->app->register(CatalogServiceProvider::class);
        $this->app->register(DeliveryServiceProvider::class);
    }
}
