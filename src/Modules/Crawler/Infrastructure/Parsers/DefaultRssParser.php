<?php

declare(strict_types=1);

namespace Modules\Crawler\Infrastructure\Parsers;

use Illuminate\Support\Collection;
use Modules\Crawler\Domain\Contracts\RssParser;
use SimpleXMLElement;

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

        if (! $xml) {
            return collect();
        }

        // RSS 2.0
        if (property_exists($xml->channel, 'item') && $xml->channel->item !== null) {
            foreach ($xml->channel->item as $item) {
                // Передаем элемент для обработки в защищенный метод, чтобы наследники могли переопределить логику
                $mapped = $this->mapItem($item);
                if ($mapped !== []) {
                    $items[] = $mapped;
                }
            }
        }
        // Atom 1.0 (например, The Verge)
        elseif (property_exists($xml, 'entry') && $xml->entry !== null) {
            foreach ($xml->entry as $entry) {
                $mapped = $this->mapAtomEntry($entry);
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
    protected function mapItem(SimpleXMLElement $item): array
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

    /**
     * @return array<string, mixed>
     */
    protected function mapAtomEntry(SimpleXMLElement $entry): array
    {
        $media = [];
        $imageUrl = null;
        $namespaces = $entry->getNamespaces(true);

        // media:content / media:group
        if (isset($namespaces['media'])) {
            $mediaNodes = $entry->children($namespaces['media']);
            if (property_exists($mediaNodes, 'content') && $mediaNodes->content !== null) {
                foreach ($mediaNodes->content as $mc) {
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
            if (property_exists($mediaNodes, 'group') && $mediaNodes->group !== null && (property_exists($mediaNodes->group, 'content') && $mediaNodes->group->content !== null)) {
                foreach ($mediaNodes->group->content as $mc) {
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
        }

        // Link handling (Atom uses <link href="..." />)
        $link = '';
        if (property_exists($entry, 'link')) {
            foreach ($entry->link as $l) {
                $rel = (string) ($l['rel'] ?? 'alternate');
                if ($rel === 'alternate' || $rel === '') {
                    $link = (string) ($l['href'] ?? '');
                    break;
                }
            }
        }

        // Content handling
        $content = (string) ($entry->content ?? '');
        if ($content === '') {
            $content = (string) ($entry->summary ?? '');
        }

        return [
            'title' => (string) ($entry->title ?? ''),
            'link' => $link,
            'description' => (string) ($entry->summary ?? ''),
            'content' => $content,
            'pubDate' => (string) ($entry->updated ?? $entry->published ?? ''),
            'guid' => (string) ($entry->id ?? ''),
            'language' => '', // Atom usually defines language at feed level
            'categories' => [], // TODO: parse categories if needed
            'author' => (string) ($entry->author->name ?? ''),
            'image_url' => $imageUrl,
            'media' => $media,
        ];
    }
}
