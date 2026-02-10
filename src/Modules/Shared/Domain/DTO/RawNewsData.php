<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\DTO;

use Carbon\CarbonImmutable;

/**
 * Стандартизированное сырьё из источника.
 */
readonly class RawNewsData
{
    public function __construct(
        public int $sourceId,
        public ?string $externalId,
        public string $title,
        public string $link,
        public string $content,
        public CarbonImmutable $publishedAt,
        public string $language,
        public array $metadata,
        public string $fingerprint,
        public ?string $rawId = null,
    ) {
    }
}
