<?php

declare(strict_types=1);

namespace Modules\Intelligence\Infrastructure\Persistence\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Modules\Intelligence\Domain\DTO\AiProviderProfile;

/**
 * @property int $id
 * @property string $slug
 * @property string $provider
 * @property string $display_name
 * @property bool $is_enabled
 * @property string $codex_home_subpath
 * @property string $default_model
 * @property string|null $default_reasoning_effort
 * @property int $max_parallel_jobs
 * @property string $auth_status
 * @property string|null $auth_mode
 * @property string|null $login_id
 * @property string|null $auth_url
 * @property string|null $account_email
 * @property string|null $plan_type
 * @property array<string, mixed>|null $rate_limit_snapshot
 * @property CarbonImmutable|null $last_status_checked_at
 * @property CarbonImmutable|null $last_authenticated_at
 * @property CarbonImmutable|null $last_error_at
 * @property string|null $last_error_message
 * @property array<string, mixed>|null $meta
 */
final class AiProviderAccount extends Model
{
    public const string PROVIDER_CHATGPT_CODEX = AiProviderProfile::PROVIDER_CHATGPT_CODEX;

    public const string STATUS_NOT_AUTHENTICATED = AiProviderProfile::STATUS_NOT_AUTHENTICATED;

    public const string STATUS_PENDING = AiProviderProfile::STATUS_PENDING;

    public const string STATUS_AUTHENTICATED = AiProviderProfile::STATUS_AUTHENTICATED;

    public const string STATUS_RATE_LIMITED = AiProviderProfile::STATUS_RATE_LIMITED;

    public const string STATUS_ERROR = AiProviderProfile::STATUS_ERROR;

    protected $table = 'ai_provider_accounts';

    protected $fillable = [
        'slug',
        'provider',
        'display_name',
        'is_enabled',
        'codex_home_subpath',
        'default_model',
        'default_reasoning_effort',
        'max_parallel_jobs',
        'auth_status',
        'auth_mode',
        'login_id',
        'auth_url',
        'account_email',
        'plan_type',
        'rate_limit_snapshot',
        'last_status_checked_at',
        'last_authenticated_at',
        'last_error_at',
        'last_error_message',
        'meta',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'max_parallel_jobs' => 'integer',
            'rate_limit_snapshot' => 'array',
            'meta' => 'array',
            'last_status_checked_at' => 'datetime',
            'last_authenticated_at' => 'datetime',
            'last_error_at' => 'datetime',
        ];
    }

    public function isAuthenticated(): bool
    {
        return $this->auth_status === self::STATUS_AUTHENTICATED;
    }

    public function isPending(): bool
    {
        return $this->auth_status === self::STATUS_PENDING;
    }

    public function statusLabel(): string
    {
        return match ($this->auth_status) {
            self::STATUS_PENDING => 'Pending',
            self::STATUS_AUTHENTICATED => 'Authenticated',
            self::STATUS_RATE_LIMITED => 'Rate Limited',
            self::STATUS_ERROR => 'Error',
            default => 'Not Authenticated',
        };
    }

    public function statusColor(): string
    {
        return match ($this->auth_status) {
            self::STATUS_PENDING => 'warning',
            self::STATUS_AUTHENTICATED => 'success',
            self::STATUS_RATE_LIMITED => 'danger',
            self::STATUS_ERROR => 'danger',
            default => 'gray',
        };
    }

    public function rateLimitUsedPercent(): ?int
    {
        return $this->windowUsedPercent($this->primaryRateLimitWindow());
    }

    public function rateLimitResetAt(): ?CarbonImmutable
    {
        return $this->windowResetAt($this->primaryRateLimitWindow());
    }

    public function weeklyRateLimitUsedPercent(): ?int
    {
        return $this->windowUsedPercent($this->weeklyRateLimitWindow());
    }

    public function weeklyRateLimitResetAt(): ?CarbonImmutable
    {
        return $this->windowResetAt($this->weeklyRateLimitWindow());
    }

    public function toProfile(): AiProviderProfile
    {
        return new AiProviderProfile(
            id: $this->id,
            slug: $this->slug,
            provider: $this->provider,
            displayName: $this->display_name,
            enabled: $this->is_enabled,
            codexHomeSubpath: $this->codex_home_subpath,
            defaultModel: $this->default_model,
            defaultReasoningEffort: $this->default_reasoning_effort,
            maxParallelJobs: $this->max_parallel_jobs,
            authStatus: $this->auth_status,
            authMode: $this->auth_mode,
            loginId: $this->login_id,
            authUrl: $this->auth_url,
            accountEmail: $this->account_email,
            planType: $this->plan_type,
            rateLimitSnapshot: is_array($this->rate_limit_snapshot) ? $this->rate_limit_snapshot : null,
            meta: is_array($this->meta) ? $this->meta : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function primaryRateLimitWindow(): array
    {
        $snapshot = $this->normalizedRateLimitSnapshot();
        $window = $snapshot['primary'] ?? null;

        return is_array($window) ? $window : [];
    }

    /**
     * @return array<string, mixed>
     */
    private function weeklyRateLimitWindow(): array
    {
        $snapshot = $this->normalizedRateLimitSnapshot();
        $secondary = $snapshot['secondary'] ?? null;

        if (is_array($secondary)) {
            return $secondary;
        }

        foreach ($snapshot as $window) {
            if (is_array($window) && ($window['windowDurationMins'] ?? null) === 10_080) {
                return $window;
            }
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $window
     */
    private function windowUsedPercent(array $window): ?int
    {
        $usedPercent = $window['usedPercent'] ?? null;

        return is_int($usedPercent) ? $usedPercent : null;
    }

    /**
     * @param  array<string, mixed>  $window
     */
    private function windowResetAt(array $window): ?CarbonImmutable
    {
        $rawValue = $window['resetsAt'] ?? null;
        if (! is_int($rawValue)) {
            return null;
        }

        $timestamp = $rawValue > 9_999_999_999 ? (int) floor($rawValue / 1000) : $rawValue;

        return CarbonImmutable::createFromTimestampUTC($timestamp);
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizedRateLimitSnapshot(): array
    {
        $snapshot = is_array($this->rate_limit_snapshot) ? $this->rate_limit_snapshot : [];

        if (isset($snapshot['rateLimits']) && is_array($snapshot['rateLimits'])) {
            /** @var array<string, mixed> $rateLimits */
            $rateLimits = $snapshot['rateLimits'];

            return $rateLimits;
        }

        return $snapshot;
    }
}
