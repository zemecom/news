<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Catalog\CatalogServiceProvider;
use Modules\Crawler\CrawlerServiceProvider;
use Modules\Delivery\DeliveryServiceProvider;
use Modules\Intelligence\IntelligenceServiceProvider;
use Override;

/**
 * Агрегирующий провайдер для регистрации всех подсистем проекта (Модульный Монолит).
 * Слой: App (Framework / Glue).
 *
 * Вместо того чтобы регистрировать каждый ServiceProvider модуля в config/app.php,
 * мы регистрируем их здесь. Это обеспечивает единую и явную точку входа
 * для всех модулей: Crawler, Intelligence, Catalog, Delivery.
 */
final class ModulesServiceProvider extends ServiceProvider
{
    #[Override]
    public function register(): void
    {
        $this->app->register(CrawlerServiceProvider::class);
        $this->app->register(IntelligenceServiceProvider::class);
        $this->app->register(CatalogServiceProvider::class);
        $this->app->register(DeliveryServiceProvider::class);
    }
}
