<?php

declare(strict_types=1);
use App\Http\Controllers\Controller;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Pages\Page;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;
use Livewire\Component;
use Modules\Crawler\Infrastructure\Parsers\DefaultRssParser;
use Modules\Crawler\Infrastructure\Parsers\Telegram\DefaultTelegramParser;

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
            DefaultRssParser::class,
            DefaultTelegramParser::class,
            'App\Providers',
            'Modules\*\Providers',
            'App\Console\Commands', // Commands might be extended
            Model::class,
            ServiceProvider::class,
            Command::class,
            Controller::class,
            Component::class,
            Filament\Resources\Resource::class,
            Page::class,
            ListRecords::class,
            CreateRecord::class,
            EditRecord::class,
            EditRecord::class,
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
