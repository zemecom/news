<?php

declare(strict_types=1);

namespace Modules\Intelligence\Domain\DTO;

final readonly class AiProviderProfile
{
    public const string PROVIDER_CHATGPT_CODEX = 'chatgpt_codex';

    public const string STATUS_NOT_AUTHENTICATED = 'not_authenticated';

    public const string STATUS_PENDING = 'pending';

    public const string STATUS_AUTHENTICATED = 'authenticated';

    public const string STATUS_RATE_LIMITED = 'rate_limited';

    public const string STATUS_ERROR = 'error';

    /**
     * @param  array<string, mixed>|null  $rateLimitSnapshot
     * @param  array<string, mixed>|null  $meta
     */
    public function __construct(
        public ?int $id,
        public string $slug,
        public string $provider,
        public string $displayName,
        public bool $enabled,
        public string $codexHomeSubpath,
        public string $defaultModel,
        public int $maxParallelJobs,
        public string $authStatus,
        public ?string $authMode = null,
        public ?string $loginId = null,
        public ?string $authUrl = null,
        public ?string $accountEmail = null,
        public ?string $planType = null,
        public ?string $defaultReasoningEffort = null,
        public ?array $rateLimitSnapshot = null,
        public ?array $meta = null,
    ) {}

    public function isAuthenticated(): bool
    {
        return $this->authStatus === self::STATUS_AUTHENTICATED;
    }

    public function isPending(): bool
    {
        return $this->authStatus === self::STATUS_PENDING;
    }
}
