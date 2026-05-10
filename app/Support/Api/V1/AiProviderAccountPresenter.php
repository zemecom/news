<?php

declare(strict_types=1);

namespace App\Support\Api\V1;

use Modules\Intelligence\Infrastructure\Persistence\Models\AiProviderAccount;

final class AiProviderAccountPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function present(AiProviderAccount $account): array
    {
        return [
            'id' => (int) $account->getKey(),
            'slug' => $account->slug,
            'provider' => $account->provider,
            'display_name' => $account->display_name,
            'is_enabled' => $account->is_enabled,
            'codex_home_subpath' => $account->codex_home_subpath,
            'default_model' => $account->default_model,
            'default_reasoning_effort' => $account->default_reasoning_effort,
            'max_parallel_jobs' => $account->max_parallel_jobs,
            'auth_status' => $account->auth_status,
            'auth_status_label' => $account->statusLabel(),
            'auth_mode' => $account->auth_mode,
            'login_id' => $account->login_id,
            'auth_url' => $account->auth_url,
            'account_email' => $account->account_email,
            'plan_type' => $account->plan_type,
            'rate_limit_snapshot' => is_array($account->rate_limit_snapshot) ? $account->rate_limit_snapshot : null,
            'last_status_checked_at' => $account->last_status_checked_at?->toIso8601String(),
            'last_authenticated_at' => $account->last_authenticated_at?->toIso8601String(),
            'last_error_at' => $account->last_error_at?->toIso8601String(),
            'last_error_message' => $account->last_error_message,
        ];
    }
}
