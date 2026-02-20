<?php

declare(strict_types=1);

namespace Modules\Catalog\Domain\Contracts;

use Modules\Shared\Domain\DTO\EnrichedNewsData;
use Modules\Shared\Domain\DTO\RawNewsData;

interface NewsRepository
{
    public function existsByFingerprint(string $fingerprint): bool;

    public function findIdByFingerprint(string $fingerprint): int;

    public function storeRaw(RawNewsData $raw): int;

    public function storeEnriched(EnrichedNewsData $enriched): void;

    /**
     * Возвращает ассоциативный массив: ['image_url' => string|null, 'media' => array|null]
     *
     * @return array{image_url: ?string, media: ?array<int|string, string>}|null
     */
    public function getMediaUrls(int $id): ?array;

    /**
     * @param  array<int|string, string>|null  $media
     */
    public function updateMedia(int $id, ?string $imageUrl, ?array $media): void;
}
