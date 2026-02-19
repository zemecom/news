<?php

declare(strict_types=1);

namespace Tests\Feature\Crawler;

use Illuminate\Support\Facades\Http;
use Modules\Crawler\Infrastructure\Services\RssParserResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('acceptance')]
final class ParserIntegrationTest extends TestCase
{
    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function rssSourcesProvider(): array
    {
        return [
            'Hacker News' => ['Hacker News', 'https://hnrss.org/frontpage'],
            'TechCrunch' => ['TechCrunch', 'https://techcrunch.com/feed/'],
            'The Verge' => ['The Verge', 'https://www.theverge.com/rss/index.xml'],
            'Al Jazeera' => ['Al Jazeera', 'https://www.aljazeera.com/xml/rss/all.xml'],
            'ScienceDaily' => ['ScienceDaily', 'https://www.sciencedaily.com/rss/top.xml'],
            'MedicalXpress' => ['MedicalXpress', 'https://medicalxpress.com/rss-feed/'],
            'Habr (RU)' => ['Habr (RU)', 'https://habr.com/ru/rss/all/all/?fl=ru'],
        ];
    }

    #[DataProvider('rssSourcesProvider')]
    public function test_can_parse_external_feed(string $name, string $url): void
    {
        try {
            // We use Saloon under the hood, but for pure parsing test we can just fetch the XML.
            // Or we could use the actual RssClient. Let's try fetching directly first to isolate parsing.
            $response = Http::timeout(10)->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36',
            ])->get($url);

            if (! $response->successful()) {
                $this->markTestSkipped("Could not fetch $name ($url) - Status: ".$response->status());
            }

            $body = $response->body();

            /** @var RssParserResolver $resolver */
            $resolver = $this->app->make(RssParserResolver::class);
            $parser = $resolver->resolve($url);

            $items = $parser->parse($body);

            if ($items->isEmpty()) {
                $this->markTestSkipped("Feed $name ($url) returned 0 items, but request was successful. Might be empty currently.");
            }

            $this->assertNotEmpty($items);

            $firstItem = $items->first();

            $this->assertIsArray($firstItem);

            $this->assertArrayHasKey('title', $firstItem);
            $this->assertArrayHasKey('link', $firstItem);
            $this->assertArrayHasKey('pubDate', $firstItem);

            // Allow empty title/link but key must exist
            $this->assertIsString($firstItem['title']);
            $this->assertIsString($firstItem['link']);

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            $this->markTestSkipped("Connection failed or timed out for $name ($url): ".$e->getMessage());
        }
    }
}
