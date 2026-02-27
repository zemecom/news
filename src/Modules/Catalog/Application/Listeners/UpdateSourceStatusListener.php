<?php

declare(strict_types=1);

namespace Modules\Catalog\Application\Listeners;

use Modules\Catalog\Domain\Contracts\SourceRepository;
use Modules\Shared\Domain\Events\SourceFetchFailed;
use Modules\Shared\Domain\Events\SourceFetchSucceeded;

/**
 * Слушатель доменных событий кроулера о статусе источника (Слой: Application).
 *
 * Пример межмодульного взаимодействия (Event-Driven Architecture):
 * Модуль Crawler генерирует событие (FetchSucceeded/FetchFailed),
 * а этот слушатель в модуле Catalog перехватывает его и обновляет счетчики
 * ошибок/успехов ресурса (таблица Sources), управляя механизмом Backoff в дальнейшем.
 */
final readonly class UpdateSourceStatusListener
{
    public function __construct(
        private SourceRepository $sourceRepository,
    ) {}

    public function handle(object $event): void
    {
        if ($event instanceof SourceFetchSucceeded) {
            $this->sourceRepository->updateSuccess($event->sourceId);
        } elseif ($event instanceof SourceFetchFailed) {
            $this->sourceRepository->updateFailure($event->sourceId);
        }
    }
}
