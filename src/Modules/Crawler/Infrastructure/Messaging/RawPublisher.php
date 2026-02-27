<?php

declare(strict_types=1);

namespace Modules\Crawler\Infrastructure\Messaging;

use Modules\Crawler\Application\Jobs\ProcessNewsJob;
use Modules\Crawler\Domain\Contracts\RawPublisher as RawPublisherContract;
use Modules\Shared\Domain\DTO\RawNewsData;

/**
 * Инфраструктурный адаптер для отправки "сырых" новостей в очередь (Слой: Infrastructure).
 *
 * Реализует контракт RawPublisherContract. Вместо прямой синхронной передачи
 * в модуль Intelligence, публикатор сериализует DTO и отправляет команду-job (`ProcessNewsJob`)
 * в RabbitMQ. Это позволяет масштабировать процесс обработки LLM независимо от краулеров.
 */
final readonly class RawPublisher implements RawPublisherContract
{
    public function publish(RawNewsData $raw): void
    {
        dispatch(new ProcessNewsJob($raw));
    }
}
