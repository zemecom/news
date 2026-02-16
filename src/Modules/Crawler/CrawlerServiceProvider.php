<?php

declare(strict_types=1);

namespace Modules\Crawler;

use Illuminate\Support\ServiceProvider;
use Modules\Crawler\Application\Actions\FeedFetcherAction;
use Modules\Crawler\Application\Services\RawNewsFactory;
use Modules\Crawler\Domain\Contracts\RawPublisher as RawPublisherContract;
use Modules\Crawler\Domain\Contracts\RssClient as RssClientContract;
use Modules\Crawler\Domain\Contracts\TelegramClient as TelegramClientContract;
use Modules\Crawler\Infrastructure\Http\RssClient;
use Modules\Crawler\Infrastructure\Http\RssConnector;
use Modules\Crawler\Infrastructure\Http\TelegramClient;
use Modules\Crawler\Infrastructure\Messaging\RawPublisher;
use Modules\Shared\Application\Services\FingerprintGenerator;

final class CrawlerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(FeedFetcherAction::class);
        $this->app->singleton(RssConnector::class);

        // RSS Parsers
        $this->app->singleton(\Modules\Crawler\Infrastructure\Parsers\DefaultRssParser::class);
        $rssParsers = [
            \Modules\Crawler\Infrastructure\Parsers\HabrRssParser::class,
            \Modules\Crawler\Infrastructure\Parsers\HackerNewsRssParser::class,
            \Modules\Crawler\Infrastructure\Parsers\TechCrunchRssParser::class,
            \Modules\Crawler\Infrastructure\Parsers\TheVergeRssParser::class,
            \Modules\Crawler\Infrastructure\Parsers\ArsTechnicaRssParser::class,
            \Modules\Crawler\Infrastructure\Parsers\BbcRssParser::class,
            \Modules\Crawler\Infrastructure\Parsers\AlJazeeraRssParser::class,
            \Modules\Crawler\Infrastructure\Parsers\ScienceDailyRssParser::class,
            \Modules\Crawler\Infrastructure\Parsers\MedicalXpressRssParser::class,
        ];
        foreach ($rssParsers as $parser) {
            $this->app->singleton($parser);
            $this->app->tag($parser, 'crawler.parsers.rss');
        }

        // Telegram Parsers
        $this->app->singleton(\Modules\Crawler\Infrastructure\Parsers\Telegram\DefaultTelegramParser::class);
        $telegramParsers = [
            \Modules\Crawler\Infrastructure\Parsers\Telegram\ToporLiveTelegramParser::class,
        ];
        foreach ($telegramParsers as $parser) {
            $this->app->singleton($parser);
            $this->app->tag($parser, 'crawler.parsers.telegram');
        }

        // Resolvers
        $this->app->bind(\Modules\Crawler\Infrastructure\Services\RssParserResolver::class, function ($app) {
            return new \Modules\Crawler\Infrastructure\Services\RssParserResolver(
                $app->tagged('crawler.parsers.rss'),
                $app->make(\Modules\Crawler\Infrastructure\Parsers\DefaultRssParser::class)
            );
        });

        $this->app->bind(\Modules\Crawler\Infrastructure\Services\TelegramParserResolver::class, function ($app) {
            return new \Modules\Crawler\Infrastructure\Services\TelegramParserResolver(
                $app->tagged('crawler.parsers.telegram'),
                $app->make(\Modules\Crawler\Infrastructure\Parsers\Telegram\DefaultTelegramParser::class)
            );
        });

        $this->app->singleton(RssClient::class);
        $this->app->singleton(TelegramClient::class);
        $this->app->singleton(RawPublisher::class);
        $this->app->singleton(RawNewsFactory::class);
        $this->app->bind(RssClientContract::class, RssClient::class);
        $this->app->bind(TelegramClientContract::class, TelegramClient::class);
        $this->app->bind(RawPublisherContract::class, RawPublisher::class);
        $this->app->singleton(FingerprintGenerator::class);

    }
}
