<?php

declare(strict_types=1);

namespace Modules\Crawler\Infrastructure\Parsers\Telegram;

use DOMDocument;
use DOMNode;
use DOMXPath;
use Illuminate\Support\Collection;
use Modules\Crawler\Domain\Contracts\TelegramParser;

class DefaultTelegramParser implements TelegramParser
{
    /**
     * @param  array{channel: string}  $context
     * @return Collection<int, array<string, mixed>>
     */
    public function parse(string $htmlBody, array $context): Collection
    {
        $channel = $context['channel'];

        $dom = new DOMDocument('1.0', 'UTF-8');
        $previousSetting = libxml_use_internal_errors(true);
        $loaded = $dom->loadHTML($htmlBody, LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previousSetting);

        if ($loaded !== true) {
            return collect();
        }

        $xpath = new DOMXPath($dom);
        $nodes = $xpath->query(
            "//*[contains(concat(' ', normalize-space(@class), ' '), ' tgme_widget_message ')][@data-post]"
        );
        if ($nodes === false || $nodes->length === 0) {
            return collect();
        }

        $items = [];
        foreach ($nodes as $node) {
            if ($node instanceof DOMNode) {
                $item = $this->mapItem($xpath, $node, $channel);
                if ($item !== []) {
                    $items[] = $item;
                }
            }
        }

        return collect($items);
    }

    public function supports(string $channel): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    protected function mapItem(DOMXPath $xpath, DOMNode $node, string $channel): array
    {
        $externalId = $this->evalString($xpath, $node, 'string(@data-post)');
        if ($externalId === '') {
            return [];
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
            $this->extractMainMessageText($xpath, $node)
        );
        $media = $this->extractMedia($xpath, $node);
        $imageUrl = null;
        foreach ($media as $file) {
            if (str_starts_with((string) ($file['type'] ?? ''), 'image/')) {
                $imageUrl = (string) $file['url'];
                break;
            }
        }

        return [
            'title' => $this->extractTitleFromNode($xpath, $node, $content, $externalId),
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

    /**
     * @return array<int, array{url:string,type:string|null}>
     */
    protected function extractMedia(DOMXPath $xpath, DOMNode $node): array
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

    protected function extractPostId(string $externalId): ?int
    {
        $parts = explode('/', $externalId);
        if (count($parts) !== 2 || ! ctype_digit($parts[1])) {
            return null;
        }

        return (int) $parts[1];
    }

    protected function extractTitleFromNode(DOMXPath $xpath, DOMNode $node, string $content, string $externalId): string
    {
        // Find the main message text element (not inside reply block)
        $textNodes = $xpath->query(
            ".//*[contains(concat(' ', normalize-space(@class), ' '), ' tgme_widget_message_text ')]"
            ."[not(ancestor::*[contains(concat(' ', normalize-space(@class), ' '), ' tgme_widget_message_reply ')])]"
            ."[not(contains(concat(' ', normalize-space(@class), ' '), ' js-message_reply_text '))]",
            $node
        );

        if ($textNodes !== false && $textNodes->length > 0) {
            $textEl = $textNodes->item(0);

            if ($textEl instanceof DOMNode) {
                // Try to find bold element within the main text
                $boldTitle = $this->evalString($xpath, $textEl, 'string(./b[1] | ./strong[1])');
                if ($boldTitle !== '') {
                    return $boldTitle;
                }
            }
        }

        return $this->extractTitle($content, $externalId);
    }

    protected function extractMainMessageText(DOMXPath $xpath, DOMNode $node): string
    {
        $textNodes = $xpath->query(
            ".//*[contains(concat(' ', normalize-space(@class), ' '), ' tgme_widget_message_text ')]"
            ."[not(ancestor::*[contains(concat(' ', normalize-space(@class), ' '), ' tgme_widget_message_reply ')])]"
            ."[not(contains(concat(' ', normalize-space(@class), ' '), ' js-message_reply_text '))]",
            $node
        );

        if ($textNodes === false || $textNodes->length === 0) {
            return '';
        }

        $firstNode = $textNodes->item(0);

        return $firstNode instanceof DOMNode ? (string) $firstNode->textContent : '';
    }

    protected function extractTitle(string $content, string $externalId): string
    {
        if ($content === '') {
            return 'Telegram post '.$externalId;
        }

        if (mb_strlen($content) <= 4096) {
            return $content;
        }

        return rtrim(mb_substr($content, 0, 4095)).'…';
    }

    protected function normalizeWhitespace(string $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }

    protected function normalizeLink(string $link): string
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

    protected function buildLinkFromExternalId(string $externalId): string
    {
        $parts = explode('/', $externalId);
        if (count($parts) !== 2) {
            return '';
        }

        return sprintf('https://t.me/%s/%s', $parts[0], $parts[1]);
    }

    protected function extractUrlFromStyle(string $style): string
    {
        if ($style === '') {
            return '';
        }

        if (! preg_match("/url\\(['\\\"]?(.*?)['\\\"]?\\)/", $style, $matches)) {
            return '';
        }

        return $this->normalizeLink((string) $matches[1]);
    }

    protected function evalString(DOMXPath $xpath, DOMNode $node, string $expression): string
    {
        $value = $xpath->evaluate($expression, $node);
        if (! is_string($value)) {
            return '';
        }

        return html_entity_decode(trim($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
