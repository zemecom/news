<?php

declare(strict_types=1);

namespace Tests\Unit\Crawler;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Modules\Crawler\Infrastructure\Http\RssConnector;
use Modules\Crawler\Infrastructure\Http\TelegramClient;
use Modules\Crawler\Infrastructure\Parsers\Telegram\DefaultTelegramParser;
use Modules\Crawler\Infrastructure\Security\SourceUrlPolicy;
use Modules\Crawler\Infrastructure\Services\TelegramParserResolver;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Tests\TestCase;

final class TelegramClientTest extends TestCase
{
    public function test_fetch_continues_pagination_when_first_page_is_newer_than_date_to(): void
    {
        Log::shouldReceive('channel')->with('crawler')->andReturnSelf();
        Log::shouldReceive('info')->andReturnNull();

        $mockClient = new MockClient([
            MockResponse::make($this->telegramPage('sample/20', 'Newer post', '2026-02-11T10:00:00+00:00')),
            MockResponse::make(json_encode($this->telegramPage('sample/10', 'Older post', '2026-02-01T10:00:00+00:00'), JSON_THROW_ON_ERROR)),
        ]);

        $connector = (new RssConnector)->withMockClient($mockClient);
        $client = new TelegramClient(
            connector: $connector,
            resolver: new TelegramParserResolver([], new DefaultTelegramParser),
            sourceUrlPolicy: new SourceUrlPolicy,
        );

        $items = $client->fetch(
            channel: '@sample',
            dateTo: Carbon::parse('2026-02-05T00:00:00+00:00'),
            limit: 1
        );

        $this->assertCount(1, $items);
        $firstItem = $items->first();
        $this->assertIsArray($firstItem);
        $this->assertSame('sample/10', $firstItem['guid'] ?? null);
        $this->assertSame('Older post', $firstItem['title'] ?? null);
        $this->assertCount(2, $mockClient->getRecordedResponses());
    }

    private function telegramPage(string $externalId, string $text, string $datetime): string
    {
        [$channel, $postId] = explode('/', $externalId, 2);

        return <<<HTML
            <html>
                <body>
                    <div class="tgme_widget_message" data-post="{$externalId}">
                        <a class="tgme_widget_message_date" href="/{$channel}/{$postId}">
                            <time datetime="{$datetime}"></time>
                        </a>
                        <div class="tgme_widget_message_text">{$text}</div>
                    </div>
                </body>
            </html>
            HTML;
    }
}
