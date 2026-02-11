<?php

declare(strict_types=1);

arch('controllers_do_not_use_models_directly', function () {
    expect('App\\Http\\Controllers')
        ->not->toUse('App\\Models');
});

arch('disallow_http_facade', function () {
    expect('App')
        ->not->toUse(['Illuminate\\Support\\Facades\\Http']);
});

arch('modules_respect_boundaries', function () {
    expect('Modules\\Crawler')
        ->not->toUse(['Modules\\Catalog', 'Modules\\Delivery', 'Modules\\Intelligence']);

    expect('Modules\\Intelligence')
        ->not->toUse(['Modules\\Delivery']);
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
            'Illuminate\\Support\\Facades\\DB',
            'Illuminate\\Support\\Facades\\Cache',
            'Illuminate\\Support\\Facades\\Http',
            'Illuminate\\Support\\Facades\\Redis',
            'Illuminate\\Database\\Eloquent\\Model',
            'Illuminate\\Database\\Query\\Builder',
        ]);
});
