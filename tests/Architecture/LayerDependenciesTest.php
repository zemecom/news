<?php

declare(strict_types=1);
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;

arch('controllers_do_not_use_models_directly', function () {
    expect('App\\Http\\Controllers')
        ->not->toUse('App\\Models');
});

arch('disallow_http_facade', function () {
    expect('App')
        ->not->toUse([Http::class]);
});

arch('modules_respect_boundaries', function () {
    $modules = ['Crawler', 'Catalog', 'Delivery', 'Intelligence'];

    foreach ($modules as $module) {
        $foreignModules = array_values(array_map(
            static fn (string $name): string => "Modules\\{$name}",
            array_filter($modules, static fn (string $name): bool => $name !== $module),
        ));

        expect("Modules\\{$module}\\Domain")
            ->not->toUse($foreignModules);

        expect("Modules\\{$module}\\Application")
            ->not->toUse($foreignModules);
    }
});

arch('domain_layer_is_pure', function () {
    $modules = ['Crawler', 'Catalog', 'Delivery', 'Intelligence', 'Shared'];

    foreach ($modules as $module) {
        expect("Modules\\{$module}\\Domain")
            ->not->toUse([
                "Modules\\{$module}\\Application",
                "Modules\\{$module}\\Infrastructure",
            ]);
    }
});

arch('application_layer_does_not_depend_on_infrastructure', function () {
    $modules = ['Crawler', 'Catalog', 'Delivery', 'Intelligence', 'Shared'];

    foreach ($modules as $module) {
        expect("Modules\\{$module}\\Application")
            ->not->toUse(["Modules\\{$module}\\Infrastructure"]);
    }
});

arch('controllers_are_thin', function () {
    expect('App\\Http\\Controllers')
        ->not->toUse([
            DB::class,
            Cache::class,
            Http::class,
            Redis::class,
            Model::class,
            Builder::class,
        ]);
});
