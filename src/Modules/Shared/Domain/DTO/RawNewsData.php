<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\DTO;

use Carbon\CarbonImmutable;

/**
 * Стандартизированное сырьё из источника.
 */
final readonly class RawNewsData
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public int $sourceId,
        public ?string $externalId,
        public string $title,
        public string $link,
        public string $content,
        public CarbonImmutable $publishedAt,
        public string $language,
        public array $metadata,
        public ?string $imageUrl,
        /** @var array<int, array{url:string,type:?string}> */
        public array $media,
        public string $fingerprint,
        public ?int $rawId = null,
    ) {}

    /**
     * Копия с подменой выбранных полей.
     *
     * @param array{
     *     sourceId?: int,
     *     externalId?: ?string,
     *     title?: string,
     *     link?: string,
     *     content?: string,
     *     publishedAt?: CarbonImmutable,
     *     language?: string,
     *     metadata?: array<string,mixed>,
     *     imageUrl?: ?string,
     *     media?: array<int, array{url:string,type:?string}>,
     *     fingerprint?: string,
     *     rawId?: ?int
     * } $overrides
     */
    public function with(array $overrides): self
    {
        return new self(
            sourceId: $overrides['sourceId'] ?? $this->sourceId,
            externalId: $overrides['externalId'] ?? $this->externalId,
            title: $overrides['title'] ?? $this->title,
            link: $overrides['link'] ?? $this->link,
            content: $overrides['content'] ?? $this->content,
            publishedAt: $overrides['publishedAt'] ?? $this->publishedAt,
            language: $overrides['language'] ?? $this->language,
            metadata: $overrides['metadata'] ?? $this->metadata,
            imageUrl: array_key_exists('imageUrl', $overrides) ? $overrides['imageUrl'] : $this->imageUrl,
            media: $overrides['media'] ?? $this->media,
            fingerprint: $overrides['fingerprint'] ?? $this->fingerprint,
            rawId: $overrides['rawId'] ?? $this->rawId,
        );
    }
}
