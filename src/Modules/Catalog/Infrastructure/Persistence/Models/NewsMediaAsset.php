<?php

declare(strict_types=1);

namespace Modules\Catalog\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $news_item_id
 * @property string $slot
 * @property int $position
 * @property string|null $source_url
 * @property string|null $source_mime_type
 * @property string $local_disk
 * @property string|null $local_path
 * @property string|null $downloaded_mime_type
 * @property int|null $file_size_bytes
 * @property string|null $checksum_sha256
 * @property string $download_status
 * @property string|null $last_error
 * @property Carbon|null $downloaded_at
 */
final class NewsMediaAsset extends Model
{
    protected $table = 'news_media_assets';

    protected $fillable = [
        'news_item_id',
        'slot',
        'position',
        'source_url',
        'source_mime_type',
        'local_disk',
        'local_path',
        'downloaded_mime_type',
        'file_size_bytes',
        'checksum_sha256',
        'download_status',
        'last_error',
        'downloaded_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'position' => 'int',
        'file_size_bytes' => 'int',
        'downloaded_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<NewsItem, $this>
     */
    public function newsItem(): BelongsTo
    {
        return $this->belongsTo(NewsItem::class, 'news_item_id');
    }
}
