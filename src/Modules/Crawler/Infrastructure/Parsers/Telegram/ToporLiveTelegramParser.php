<?php

declare(strict_types=1);

namespace Modules\Crawler\Infrastructure\Parsers\Telegram;

class ToporLiveTelegramParser extends DefaultTelegramParser
{
    public function supports(string $channel): bool
    {
        // Проверяем как чистое имя, так и c @, так и URL (на случай если передали URL)
        return str_contains($channel, 'toporlive');
    }
}
