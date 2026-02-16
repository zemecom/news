<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\DTO;

use Modules\Shared\Domain\Enum\NewsStatus;

readonly class EnrichedNewsData
{
    /**
     * @param  array<int, string>  $tags
     */
    public function __construct(
        public string $rawId,
        public ?string $titleGenerated,
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

    /**
     * Копия с подменой выбранных полей.
     *
     * @param array{
     *     rawId?: string,
     *     titleGenerated?: ?string,
     *     contentTranslated?: string,
     *     sentiment?: int,
     *     category?: string,
     *     tags?: array<int, string>,
     *     importance?: bool,
     *     status?: NewsStatus,
     *     moderationReason?: ?string,
     *     fingerprint?: string
     * } $overrides
     */
    public function with(array $overrides): self
    {
        return new self(
            rawId: $overrides['rawId'] ?? $this->rawId,
            titleGenerated: $overrides['titleGenerated'] ?? $this->titleGenerated,
            contentTranslated: $overrides['contentTranslated'] ?? $this->contentTranslated,
            sentiment: $overrides['sentiment'] ?? $this->sentiment,
            category: $overrides['category'] ?? $this->category,
            tags: $overrides['tags'] ?? $this->tags,
            importance: $overrides['importance'] ?? $this->importance,
            status: $overrides['status'] ?? $this->status,
            moderationReason: $overrides['moderationReason'] ?? $this->moderationReason,
            fingerprint: $overrides['fingerprint'] ?? $this->fingerprint,
        );
    }
}
