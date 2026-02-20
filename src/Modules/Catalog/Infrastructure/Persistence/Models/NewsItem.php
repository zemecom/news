<?php

declare(strict_types=1);

namespace Modules\Catalog\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string|null $image_url
 * @property array<int|string, string>|null $media
 */
final class NewsItem extends Model
{
    // use \Illuminate\Database\Eloquent\Concerns\HasUuids;

    protected $table = 'news_items';

    protected $primaryKey = 'id';

    protected $fillable = [
        'id',
        'source_id',
        'title_original',
        'content_original',
        'title_generated',
        'content_translated',
        'image_url',
        'media',
        'sentiment_score',
        'tags',
        'is_important',
        'status',
        'source_metadata',
        'raw_fingerprint',
        'moderation_reason',
        'published_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'tags' => 'array',
        'source_metadata' => 'array',
        'is_important' => 'boolean',
        'published_at' => 'datetime',
        'media' => 'array',
    ];
}
