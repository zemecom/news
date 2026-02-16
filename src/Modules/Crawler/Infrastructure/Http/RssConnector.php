<?php

declare(strict_types=1);

namespace Modules\Crawler\Infrastructure\Http;

use Saloon\Http\Connector;

final class RssConnector extends Connector
{
    public function resolveBaseUrl(): string
    {
        return '';
    }

    public function defaultConfig(): array
    {
        return [
            'timeout' => 60,
            'allow_redirects' => [
                'max' => 3,
            ],
        ];
    }
}
