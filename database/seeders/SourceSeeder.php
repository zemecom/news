<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Catalog\Infrastructure\Persistence\Models\Source;

final class SourceSeeder extends Seeder
{
    public function run(): void
    {
        $sources = [
            ['name' => 'Tech RSS', 'url' => 'https://example.com/rss/tech', 'type' => 'rss', 'language_default' => 'en', 'cron_expression' => '*/10 * * * *'],
            ['name' => 'Economy Daily', 'url' => 'https://example.com/rss/economy', 'type' => 'rss', 'language_default' => 'en', 'cron_expression' => '*/15 * * * *'],
            ['name' => 'Politics Wire', 'url' => 'https://example.com/rss/politics', 'type' => 'rss', 'language_default' => 'en', 'cron_expression' => '*/20 * * * *'],
            ['name' => 'IT Новости', 'url' => 'https://example.com/rss/it', 'type' => 'rss', 'language_default' => 'ru', 'cron_expression' => '*/10 * * * *'],
            ['name' => 'Мир', 'url' => 'https://example.com/rss/world', 'type' => 'rss', 'language_default' => 'ru', 'cron_expression' => '*/30 * * * *'],
        ];

        foreach ($sources as $source) {
            Source::query()->updateOrCreate(['url' => $source['url']], $source);
        }
    }
}
