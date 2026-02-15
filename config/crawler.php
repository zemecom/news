<?php

declare(strict_types=1);

return [
    // Пустой список = разрешить все домены
    'allowlist' => [
        // 'example.com',
    ],
    'telegram' => [
        'max_items' => (int) env('TELEGRAM_FETCH_LIMIT', 50),
    ],
];
