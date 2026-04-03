<?php

declare(strict_types=1);

namespace Modules\Catalog\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string $url
 * @property string|null $language_default
 */
final class Source extends Model
{
    protected $table = 'sources';

    protected $fillable = [
        'name',
        'url',
        'type',
        'language_default',
        'cron_expression',
        'is_active',
        'retry_backoff_state',
        'last_success_at',
        'last_error_at',
        'error_streak',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'is_active' => 'boolean',
        'retry_backoff_state' => 'array',
        'last_success_at' => 'datetime',
        'last_error_at' => 'datetime',
    ];

    /** @return HasMany<NewsItem, $this> */
    public function newsItems(): HasMany
    {
        return $this->hasMany(NewsItem::class, 'source_id');
    }
}
