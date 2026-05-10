<?php

declare(strict_types=1);

namespace App\Support\Api\V1;

use Modules\Catalog\Infrastructure\Persistence\Models\Source;

final class SourcePresenter
{
    /**
     * @param  array<string, mixed>  $source
     * @return array<string, mixed>
     */
    public static function public(array $source): array
    {
        return [
            'id' => (int) ($source['id'] ?? 0),
            'name' => (string) ($source['name'] ?? ''),
        ];
    }

    /**
     * @param  array<string, mixed>|Source  $source
     * @return array<string, mixed>
     */
    public static function admin(array|Source $source): array
    {
        if ($source instanceof Source) {
            return [
                'id' => (int) $source->getKey(),
                'name' => $source->name,
                'url' => $source->url,
                'type' => $source->type,
                'language_default' => $source->language_default,
                'cron_expression' => $source->cron_expression,
                'is_active' => (bool) $source->is_active,
                'retry_backoff_state' => is_array($source->retry_backoff_state) ? $source->retry_backoff_state : null,
                'last_success_at' => $source->last_success_at?->toIso8601String(),
                'last_error_at' => $source->last_error_at?->toIso8601String(),
                'error_streak' => (int) $source->error_streak,
            ];
        }

        return [
            'id' => (int) ($source['id'] ?? 0),
            'name' => (string) ($source['name'] ?? ''),
            'url' => (string) ($source['url'] ?? ''),
            'type' => (string) ($source['type'] ?? ''),
            'language_default' => $source['language_default'] ?? null,
            'cron_expression' => $source['cron_expression'] ?? null,
            'is_active' => (bool) ($source['is_active'] ?? false),
            'retry_backoff_state' => is_array($source['retry_backoff_state'] ?? null) ? $source['retry_backoff_state'] : null,
            'last_success_at' => $source['last_success_at'] ?? null,
            'last_error_at' => $source['last_error_at'] ?? null,
            'error_streak' => (int) ($source['error_streak'] ?? 0),
        ];
    }
}
