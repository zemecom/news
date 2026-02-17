<?php

declare(strict_types=1);

namespace Modules\Crawler\Infrastructure\Parsers;

class HabrRssParser extends DefaultRssParser
{
    #[\Override]
    public function supports(string $url): bool
    {
        return str_contains($url, 'habr.com');
    }

    #[\Override]
    protected function mapItem(\SimpleXMLElement $item): array
    {
        $mapped = parent::mapItem($item);
        if ($mapped === []) {
            return [];
        }

        $descriptionHtml = (string) ($item->description ?? '');
        $imageUrl = $mapped['image_url'];

        // Fallback: extract image from <img> tag in description HTML
        if ($imageUrl === null && $descriptionHtml !== '' && preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', $descriptionHtml, $matches)) {
            $imageUrl = $matches[1];
        }

        // Extract clean text from description (remove img tags, "Читать далее" links, strip HTML)
        $descriptionText = $descriptionHtml;
        $descriptionText = preg_replace('/<img[^>]*>/i', '', $descriptionText) ?? $descriptionText;
        $descriptionText = preg_replace('/<a[^>]*>\s*Читать далее\s*<\/a>/iu', '', $descriptionText) ?? $descriptionText;
        $descriptionText = trim(strip_tags($descriptionText));

        $mapped['image_url'] = $imageUrl;
        $mapped['description'] = $descriptionText;

        return $mapped;
    }
}
