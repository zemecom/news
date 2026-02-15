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
            ['name' => 'Hacker News', 'url' => 'https://hnrss.org/frontpage', 'type' => 'rss', 'language_default' => 'en', 'cron_expression' => '*/5 * * * *'],
            ['name' => 'TechCrunch', 'url' => 'https://techcrunch.com/feed/', 'type' => 'rss', 'language_default' => 'en', 'cron_expression' => '*/10 * * * *'],
            ['name' => 'The Verge', 'url' => 'https://www.theverge.com/rss/index.xml', 'type' => 'rss', 'language_default' => 'en', 'cron_expression' => '*/10 * * * *'],
            ['name' => 'Ars Technica', 'url' => 'https://arstechnica.com/feed/', 'type' => 'rss', 'language_default' => 'en', 'cron_expression' => '*/15 * * * *'],
            ['name' => 'BBC World', 'url' => 'https://feeds.bbci.co.uk/news/world/rss.xml', 'type' => 'rss', 'language_default' => 'en', 'cron_expression' => '*/15 * * * *'],
            ['name' => 'BBC Business', 'url' => 'https://feeds.bbci.co.uk/news/business/rss.xml', 'type' => 'rss', 'language_default' => 'en', 'cron_expression' => '*/15 * * * *'],
            ['name' => 'Al Jazeera', 'url' => 'https://www.aljazeera.com/xml/rss/all.xml', 'type' => 'rss', 'language_default' => 'en', 'cron_expression' => '*/20 * * * *'],
            ['name' => 'ScienceDaily', 'url' => 'https://www.sciencedaily.com/rss/top.xml', 'type' => 'rss', 'language_default' => 'en', 'cron_expression' => '*/30 * * * *'],
            ['name' => 'MedicalXpress', 'url' => 'https://medicalxpress.com/rss-feed/', 'type' => 'rss', 'language_default' => 'en', 'cron_expression' => '*/30 * * * *'],
            ['name' => 'Habr (RU)', 'url' => 'https://habr.com/ru/rss/all/all/?fl=ru', 'type' => 'rss', 'language_default' => 'ru', 'cron_expression' => '*/10 * * * *'],
            ['name' => 'TOPOR Live', 'url' => 'https://t.me/toporlive', 'type' => 'telegram', 'language_default' => 'ru', 'cron_expression' => '*/2 * * * *'],
        ];

        foreach ($sources as $source) {
            Source::query()->updateOrCreate(['url' => $source['url']], $source);
        }
    }
}
