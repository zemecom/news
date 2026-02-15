<?php

declare(strict_types=1);

namespace Modules\Crawler\Infrastructure\Http;

use DOMDocument;
use DOMNode;
use DOMXPath;
use Illuminate\Support\Collection;
use Modules\Crawler\Domain\Contracts\TelegramClient as TelegramClientContract;
use Saloon\Enums\Method;
use Saloon\Http\Response;

final class TelegramClient implements TelegramClientContract
{
    public function __construct(private RssConnector $connector) {}

    public function fetch(string $channel): Collection
    {
        $url = $this->resolveFeedUrl($channel);
        $this->assertAllowedHost($url);

        $response = $this->connector->send(
            new class($url) extends \Saloon\Http\Request
            {
                protected Method $method = Method::GET;

                public function __construct(private string $url) {}

                public function resolveEndpoint(): string
                {
                    return $this->url;
                }

                public function defaultHeaders(): array
                {
                    return [
                        'User-Agent' => 'SmartNewsBot/1.0',
                    ];
                }
            }
        );

        return $this->mapToItems($response, $channel);
    }

    private function resolveFeedUrl(string $channel): string
    {
        $channel = trim($channel);
        if ($channel === '') {
            throw new \InvalidArgumentException('Telegram channel is empty.');
        }

        if (str_starts_with($channel, '@')) {
            return 'https://t.me/s/'.$this->normalizeChannelName(substr($channel, 1));
        }

        if (filter_var($channel, FILTER_VALIDATE_URL) !== false) {
            $parts = parse_url($channel);
            $host = (string) ($parts['host'] ?? '');
            $path = trim((string) ($parts['path'] ?? ''), '/');

            if ($host !== '' && str_contains($host, 't.me')) {
                if (str_starts_with($path, 's/')) {
                    return 'https://t.me/'.$path;
                }

                return 'https://t.me/s/'.$this->normalizeChannelName($path);
            }
        }

        return 'https://t.me/s/'.$this->normalizeChannelName($channel);
    }

    private function normalizeChannelName(string $name): string
    {
        $normalized = preg_replace('/[^a-zA-Z0-9_]/', '', $name) ?? '';
        if ($normalized === '') {
            throw new \InvalidArgumentException('Invalid telegram channel name.');
        }

        return $normalized;
    }

    private function assertAllowedHost(string $url): void
    {
        $host = parse_url($url, PHP_URL_HOST);
        $allowlist = config('crawler.allowlist', []);

        if ($host === null || $host === '') {
            throw new \InvalidArgumentException('Invalid Telegram URL host.');
        }

        if ($allowlist !== [] && ! in_array($host, $allowlist, true)) {
            throw new \InvalidArgumentException('Telegram host is not in allowlist.');
        }
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function mapToItems(Response $response, string $channel): Collection
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $previousSetting = libxml_use_internal_errors(true);
        $loaded = $dom->loadHTML($response->body(), LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previousSetting);

        if ($loaded !== true) {
            return collect();
        }

        $xpath = new DOMXPath($dom);
        $nodes = $xpath->query(
            "//*[contains(concat(' ', normalize-space(@class), ' '), ' tgme_widget_message_wrap ')][@data-post]"
        );
        if ($nodes === false || $nodes->length === 0) {
            return collect();
        }

        $items = [];
        foreach ($nodes as $node) {
            $externalId = $this->evalString($xpath, $node, 'string(@data-post)');
            if ($externalId === '') {
                continue;
            }

            $link = $this->normalizeLink(
                $this->evalString(
                    $xpath,
                    $node,
                    "string(.//*[contains(concat(' ', normalize-space(@class), ' '), ' tgme_widget_message_date ')]/@href)"
                )
            );
            $date = $this->evalString(
                $xpath,
                $node,
                "string(.//*[contains(concat(' ', normalize-space(@class), ' '), ' tgme_widget_message_date ')]/time/@datetime)"
            );
            $content = $this->normalizeWhitespace(
                $this->evalString(
                    $xpath,
                    $node,
                    "string(.//*[contains(concat(' ', normalize-space(@class), ' '), ' tgme_widget_message_text ')])"
                )
            );
            $media = $this->extractMedia($xpath, $node);
            $imageUrl = null;
            foreach ($media as $file) {
                if (str_starts_with((string) ($file['type'] ?? ''), 'image/')) {
                    $imageUrl = (string) $file['url'];

                    break;
                }
            }

            $items[] = [
                'title' => $this->extractTitle($content, $externalId),
                'link' => $link !== '' ? $link : $this->buildLinkFromExternalId($externalId),
                'description' => $content,
                'content' => $content,
                'pubDate' => $date !== '' ? $date : now()->toIso8601String(),
                'guid' => $externalId,
                'language' => '',
                'categories' => ['telegram'],
                'author' => $channel,
                'image_url' => $imageUrl,
                'media' => $media,
            ];
        }

        return collect($items);
    }

    /**
     * @return array<int, array{url:string,type:string|null}>
     */
    private function extractMedia(DOMXPath $xpath, DOMNode $node): array
    {
        $media = [];

        $photoStyle = $this->evalString(
            $xpath,
            $node,
            "string(.//*[contains(concat(' ', normalize-space(@class), ' '), ' tgme_widget_message_photo_wrap ')]/@style)"
        );
        $photo = $this->extractUrlFromStyle($photoStyle);
        if ($photo !== '') {
            $media[] = ['url' => $photo, 'type' => 'image/jpeg'];
        }

        $videoThumbStyle = $this->evalString(
            $xpath,
            $node,
            "string(.//*[contains(concat(' ', normalize-space(@class), ' '), ' tgme_widget_message_video_thumb ')]/@style)"
        );
        $videoThumb = $this->extractUrlFromStyle($videoThumbStyle);
        if ($videoThumb !== '') {
            $media[] = ['url' => $videoThumb, 'type' => 'image/jpeg'];
        }

        $documentLink = $this->normalizeLink(
            $this->evalString(
                $xpath,
                $node,
                "string(.//*[contains(concat(' ', normalize-space(@class), ' '), ' tgme_widget_message_document_wrap ')]//a/@href)"
            )
        );
        if ($documentLink !== '') {
            $media[] = ['url' => $documentLink, 'type' => null];
        }

        return $media;
    }

    private function extractTitle(string $content, string $externalId): string
    {
        if ($content === '') {
            return 'Telegram post '.$externalId;
        }

        if (mb_strlen($content) <= 140) {
            return $content;
        }

        return rtrim(mb_substr($content, 0, 139)).'…';
    }

    private function normalizeWhitespace(string $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }

    private function normalizeLink(string $link): string
    {
        $link = trim($link);
        if ($link === '') {
            return '';
        }

        if (str_starts_with($link, '//')) {
            return 'https:'.$link;
        }

        if (str_starts_with($link, '/')) {
            return 'https://t.me'.$link;
        }

        return $link;
    }

    private function buildLinkFromExternalId(string $externalId): string
    {
        $parts = explode('/', $externalId);
        if (count($parts) !== 2) {
            return '';
        }

        return sprintf('https://t.me/%s/%s', $parts[0], $parts[1]);
    }

    private function extractUrlFromStyle(string $style): string
    {
        if ($style === '') {
            return '';
        }

        if (! preg_match("/url\\(['\\\"]?(.*?)['\\\"]?\\)/", $style, $matches)) {
            return '';
        }

        return $this->normalizeLink((string) $matches[1]);
    }

    private function evalString(DOMXPath $xpath, DOMNode $node, string $expression): string
    {
        $value = $xpath->evaluate($expression, $node);
        if (! is_string($value)) {
            return '';
        }

        return html_entity_decode(trim($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
