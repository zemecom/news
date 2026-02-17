<?php

declare(strict_types=1);

namespace Modules\Crawler\Infrastructure\Parsers;

use Illuminate\Support\Collection;
use Modules\Crawler\Domain\Contracts\RssParser;

class DefaultRssParser implements RssParser
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function parse(string $xmlBody): Collection
    {
        // Простая обёртка: парсинг RSS/Atom
        // Используем @ для подавления предупреждений при некорректном XML
        $xml = @simplexml_load_string($xmlBody, 'SimpleXMLElement', LIBXML_NOCDATA);
        $items = [];

        if ($xml && (property_exists($xml->channel, 'item') && $xml->channel->item !== null)) {
            foreach ($xml->channel->item as $item) {
                // Передаем элемент для обработки в защищенный метод, чтобы наследники могли переопределить логику
                $mapped = $this->mapItem($item);
                if ($mapped !== []) {
                    $items[] = $mapped;
                }
            }
        }

        return collect($items);
    }

    public function supports(string $url): bool
    {
        return true; // Базовый парсер поддерживает всё как fallback
    }

    /**
     * @return array<string, mixed>
     */
    protected function mapItem(\SimpleXMLElement $item): array
    {
        $media = [];
        $imageUrl = null;

        // RSS enclosure
        if (property_exists($item, 'enclosure') && $item->enclosure !== null) {
            foreach ($item->enclosure as $enclosure) {
                $url = (string) ($enclosure['url'] ?? '');
                $type = (string) ($enclosure['type'] ?? '');
                if ($url !== '') {
                    $media[] = ['url' => $url, 'type' => $type ?: null];
                    if ($imageUrl === null && str_starts_with($type, 'image/')) {
                        $imageUrl = $url;
                    }
                }
            }
        }

        // media:content
        if (isset($item->{'media:content'})) {
            foreach ($item->{'media:content'} as $mc) {
                $url = (string) ($mc['url'] ?? '');
                $type = (string) ($mc['type'] ?? '');
                if ($url !== '') {
                    $media[] = ['url' => $url, 'type' => $type ?: null];
                    if ($imageUrl === null && str_starts_with($type, 'image/')) {
                        $imageUrl = $url;
                    }
                }
            }
        }

        return [
            'title' => (string) ($item->title ?? ''),
            'link' => (string) ($item->link ?? ''),
            'description' => (string) ($item->description ?? ''),
            'content' => (string) ($item->{'content:encoded'} ?? ''),
            'pubDate' => (string) ($item->pubDate ?? ''),
            'guid' => (string) ($item->guid ?? ''),
            'language' => (string) ($item->language ?? ''),
            'categories' => array_map(strval(...), iterator_to_array($item->category ?? [])),
            'author' => (string) ($item->author ?? ''),
            'image_url' => $imageUrl,
            'media' => $media,
        ];
    }
}
