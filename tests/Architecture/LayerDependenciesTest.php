<?php

declare(strict_types=1);

arch('controllers_do_not_use_models_directly', function () {
    expect('App\\Http\\Controllers')
        ->not->toUse('App\\Models');
});

arch('disallow_http_facade', function () {
    expect('App')
        ->not->toUse([\Illuminate\Support\Facades\Http::class]);
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
            \Illuminate\Support\Facades\DB::class,
            \Illuminate\Support\Facades\Cache::class,
            \Illuminate\Support\Facades\Http::class,
            \Illuminate\Support\Facades\Redis::class,
            \Illuminate\Database\Eloquent\Model::class,
            \Illuminate\Database\Query\Builder::class,
        ]);
});
