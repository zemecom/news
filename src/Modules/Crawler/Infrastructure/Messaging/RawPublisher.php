<?php

declare(strict_types=1);

namespace Modules\Crawler\Infrastructure\Messaging;

use Modules\Crawler\Application\Jobs\ProcessNewsJob;
use Modules\Crawler\Domain\Contracts\RawPublisher as RawPublisherContract;
use Modules\Shared\Domain\DTO\RawNewsData;

final readonly class RawPublisher implements RawPublisherContract
{
    public function __construct() {}

    public function publish(RawNewsData $raw): void
    {
        // Отправляем в очередь задачу на обработку новости (делегируем через Job внутри Crawler)
        dispatch(new ProcessNewsJob($raw));
    }
}
