<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\DTO;

use Modules\Shared\Domain\Enum\NewsStatus;

readonly class EnrichedNewsData
{
    public function __construct(
        public string $rawId,
        public string $titleGenerated,
        public string $contentTranslated,
        public int $sentiment,
        public string $category,
        public array $tags,
        public bool $importance,
        public NewsStatus $status,
        public ?string $moderationReason,
        public string $fingerprint,
    ) {
    }
}
