<?php

declare(strict_types=1);

use Pest\Arch\Arch;

it('controllers_do_not_use_models_directly', function () {
    Arch::expect('App\\Http\\Controllers')
        ->not->toUse('App\\Models');
});

it('disallow_http_facade', function () {
    Arch::expect('App')
        ->not->toUse(['Illuminate\\Support\\Facades\\Http']);
});

it('modules_respect_boundaries', function () {
    Arch::expect('Modules\\Crawler')
        ->not->toUse(['Modules\\Catalog', 'Modules\\Delivery', 'Modules\\Intelligence']);

    Arch::expect('Modules\\Intelligence')
        ->not->toUse(['Modules\\Delivery']);
});
