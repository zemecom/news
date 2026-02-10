<?php

declare(strict_types=1);

namespace Modules\Catalog\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

final class NewsItem extends Model
{
    protected $table = 'news_items';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'source_id',
        'title_original',
        'content_original',
        'title_generated',
        'content_translated',
        'sentiment_score',
        'tags',
        'is_important',
        'status',
        'source_metadata',
        'raw_fingerprint',
        'moderation_reason',
        'published_at',
    ];

    protected $casts = [
        'tags' => 'array',
        'source_metadata' => 'array',
        'is_important' => 'boolean',
        'published_at' => 'datetime',
    ];
}
