<?php

declare(strict_types=1);

namespace Modules\Catalog\Domain\Contracts;

use Modules\Shared\Domain\Contracts\NewsStore;

interface NewsRepository extends NewsStore
{
    public function findIdByFingerprint(string $fingerprint): int;

    /**
     * Возвращает исходные медиа-ссылки из сырой записи новости.
     *
     * @return array{image_url: ?string, media: array<int, mixed>}|null
     */
    public function getMediaUrls(int $id): ?array;
}
