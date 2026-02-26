<?php

declare(strict_types=1);

$globalAllowlist = array_values(array_filter(array_map(
    static fn (string $host): string => mb_strtolower(trim($host)),
    explode(',', (string) env('CRAWLER_ALLOWLIST', ''))
)));
$rssAllowlist = array_values(array_filter(array_map(
    static fn (string $host): string => mb_strtolower(trim($host)),
    explode(',', (string) env('CRAWLER_RSS_ALLOWLIST', ''))
)));
$telegramAllowlist = array_values(array_filter(array_map(
    static fn (string $host): string => mb_strtolower(trim($host)),
    explode(',', (string) env('CRAWLER_TELEGRAM_ALLOWLIST', 't.me'))
)));

return [
    // Allowlist policy:
    // - `global` применяется ко всем типам источников
    // - `rss`/`telegram` расширяют (или переопределяют) глобальный список для типа
    // - пустой итоговый список для типа => разрешены любые hosts
    'allowlist' => [
        'global' => $globalAllowlist,
        'rss' => $rssAllowlist,
        'telegram' => $telegramAllowlist,
    ],
    'security' => [
        // Ограничение схем URL источников (SSRF hardening)
        'allowed_source_schemes' => array_values(array_filter(array_map(
            static fn (string $scheme): string => mb_strtolower(trim($scheme)),
            explode(',', (string) env('CRAWLER_ALLOWED_SOURCE_SCHEMES', 'https'))
        ))),
        // Разрешенные схемы для ссылок/медиа внутри контента
        'allowed_url_schemes' => array_values(array_filter(array_map(
            static fn (string $scheme): string => mb_strtolower(trim($scheme)),
            explode(',', (string) env('CRAWLER_ALLOWED_URL_SCHEMES', 'http,https'))
        ))),
        'deny_private_hosts' => (bool) env('CRAWLER_DENY_PRIVATE_HOSTS', true),
        'sanitization' => [
            'max_text_length' => (int) env('CRAWLER_SANITIZE_MAX_TEXT_LENGTH', 20000),
            'max_category_length' => (int) env('CRAWLER_SANITIZE_MAX_CATEGORY_LENGTH', 64),
        ],
    ],
    'runtime' => [
        'health' => [
            'enabled' => (bool) env('CRAWLER_HEALTH_BACKOFF_ENABLED', true),
            'backoff_after_streak' => (int) env('CRAWLER_HEALTH_BACKOFF_AFTER_STREAK', 2),
            'base_backoff_minutes' => (int) env('CRAWLER_HEALTH_BASE_BACKOFF_MINUTES', 5),
            'max_backoff_minutes' => (int) env('CRAWLER_HEALTH_MAX_BACKOFF_MINUTES', 720),
        ],
    ],
    'telegram' => [
        'max_items' => (int) env('TELEGRAM_FETCH_LIMIT', 50),
    ],
];
