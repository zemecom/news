<?php

declare(strict_types=1);

namespace Modules\Crawler\Application\Services;

final class IncomingContentSanitizer
{
    public function sanitizeText(string $value): string
    {
        if ($value === '') {
            return '';
        }

        $value = $this->stripDangerousHtml($value);
        $value = $this->replaceBlockTagsWithBreaks($value);
        $value = strip_tags($value);
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = str_replace("\u{00A0}", ' ', $value);
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? $value;
        $value = preg_replace("/\r\n?/", "\n", $value) ?? $value;
        $value = preg_replace('/[ \t]*\n[ \t]*/u', "\n", $value) ?? $value;
        $value = preg_replace('/[ \t]+/u', ' ', $value) ?? $value;
        $value = preg_replace("/\n{3,}/u", "\n\n", $value) ?? $value;

        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $maxLength = (int) config('crawler.security.sanitization.max_text_length', 20000);
        if ($maxLength > 0 && mb_strlen($value) > $maxLength) {
            return rtrim(mb_substr($value, 0, $maxLength)).'…';
        }

        return $value;
    }

    public function sanitizeUrl(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim(html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($value === '') {
            return null;
        }

        if (str_starts_with($value, '//')) {
            $value = 'https:'.$value;
        }

        $scheme = parse_url($value, PHP_URL_SCHEME);
        $host = parse_url($value, PHP_URL_HOST);

        if (! is_string($scheme) || ! is_string($host) || trim($host) === '') {
            return null;
        }

        $allowedSchemes = config('crawler.security.allowed_url_schemes', ['http', 'https']);
        if (! is_array($allowedSchemes) || $allowedSchemes === []) {
            $allowedSchemes = ['http', 'https'];
        }

        $normalizedScheme = mb_strtolower(trim($scheme));
        $normalizedAllowed = [];
        foreach ($allowedSchemes as $allowedScheme) {
            if (is_string($allowedScheme) && trim($allowedScheme) !== '') {
                $normalizedAllowed[] = mb_strtolower(trim($allowedScheme));
            }
        }

        if ($normalizedAllowed !== [] && ! in_array($normalizedScheme, $normalizedAllowed, true)) {
            return null;
        }

        return $value;
    }

    /**
     * @return array<int, string>
     */
    public function sanitizeLinks(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $links = [];
        foreach ($value as $item) {
            if (! is_string($item)) {
                continue;
            }

            $url = $this->sanitizeUrl($item);
            if ($url !== null) {
                $links[$url] = true;
            }
        }

        return array_keys($links);
    }

    /**
     * @return array<int, string>
     */
    public function sanitizeCategories(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $maxLength = (int) config('crawler.security.sanitization.max_category_length', 64);
        $categories = [];
        foreach ($value as $item) {
            if (! is_scalar($item)) {
                continue;
            }

            $category = $this->sanitizeText((string) $item);
            if ($category === '') {
                continue;
            }

            if ($maxLength > 0 && mb_strlen($category) > $maxLength) {
                $category = mb_substr($category, 0, $maxLength);
            }

            $categories[$category] = true;
        }

        return array_keys($categories);
    }

    /**
     * @return array<int, array{url:string,type:?string}>
     */
    public function sanitizeMedia(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $media = [];
        foreach ($value as $item) {
            if (! is_array($item)) {
                continue;
            }

            $url = $this->sanitizeUrl($this->normalizeString($item['url'] ?? null));
            if ($url === null) {
                continue;
            }

            $type = $this->normalizeMediaType($item['type'] ?? null);
            $media[$url] = ['url' => $url, 'type' => $type];
        }

        return array_values($media);
    }

    private function stripDangerousHtml(string $value): string
    {
        $value = preg_replace('/<\s*(script|style|iframe|object|embed|svg|math)\b[^>]*>.*?<\s*\/\s*\1\s*>/is', ' ', $value) ?? $value;

        return preg_replace('/<!--.*?-->/s', ' ', $value) ?? $value;
    }

    private function replaceBlockTagsWithBreaks(string $value): string
    {
        return preg_replace('/<\s*(br|\/p|\/div|\/li|\/h[1-6]|\/blockquote)\b[^>]*>/i', "\n", $value) ?? $value;
    }

    private function normalizeString(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized === '' ? null : $normalized;
    }

    private function normalizeMediaType(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $normalized = mb_strtolower(trim((string) $value));
        if ($normalized === '') {
            return null;
        }

        if (! preg_match('/^[a-z0-9!#$&^_.+-]+\/[a-z0-9!#$&^_.+-]+$/', $normalized)) {
            return null;
        }

        return $normalized;
    }
}
