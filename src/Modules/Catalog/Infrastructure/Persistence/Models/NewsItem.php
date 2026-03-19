<?php

declare(strict_types=1);

namespace Modules\Catalog\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $title_original
 * @property string|null $title_generated
 * @property string|null $image_url
 * @property array<int|string, mixed>|null $media
 * @property array<int, string>|null $tags
 * @property array<string, mixed>|null $source_metadata
 * @property string $raw_fingerprint
 * @property \Illuminate\Support\Carbon|null $published_at
 * @property Source $source
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

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<Source, $this>
     */
    public function source(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Source::class, 'source_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<NewsMediaAsset, $this>
     */
    public function mediaAssets(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(NewsMediaAsset::class, 'news_item_id');
    }
}
