<?php

declare(strict_types=1);

arch('code_style', function () {
    expect(['App', 'Modules'])
        ->toUseStrictTypes()
        ->not()->toUse(['dd', 'dump', 'ray', 'die', 'echo', 'print_r', 'var_dump']);
});

arch('final_classes', function () {
    expect(['App', 'Modules'])
        ->classes()
        ->toBeFinal() // Enforce final by default
        ->ignoring([
            // Framework specific classes that often require inheritance
            'App\Models',
            'Modules\*\Infrastructure\Persistence\Models',
            \Modules\Crawler\Infrastructure\Parsers\DefaultRssParser::class,
            \Modules\Crawler\Infrastructure\Parsers\Telegram\DefaultTelegramParser::class,
            'App\Providers',
            'Modules\*\Providers',
            'App\Console\Commands', // Commands might be extended
            \Illuminate\Database\Eloquent\Model::class,
            \Illuminate\Support\ServiceProvider::class,
            \Illuminate\Console\Command::class,
            \App\Http\Controllers\Controller::class,
            \Livewire\Component::class,
            \Filament\Resources\Resource::class,
            \Filament\Resources\Pages\Page::class,
            \Filament\Resources\Pages\ListRecords::class,
            \Filament\Resources\Pages\CreateRecord::class,
            \Filament\Resources\Pages\EditRecord::class,
            \Filament\Resources\Pages\EditRecord::class,
            // Middleware
            'App\Http\Middleware',
            // Filament
            'App\Filament',
            // Livewire
            'App\Livewire',
            // Tests
            'Tests',
        ]);
});
