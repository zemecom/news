<?php

declare(strict_types=1);

namespace Modules\Catalog\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $name
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

    /** @return \Illuminate\Database\Eloquent\Relations\HasMany<NewsItem, $this> */
    public function newsItems(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(NewsItem::class, 'source_id');
    }
}
