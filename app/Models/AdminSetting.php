<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Override;

/**
 * @property int $id
 * @property bool $news_auto_refresh_enabled
 * @property int $news_auto_refresh_interval_seconds
 */
final class AdminSetting extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'news_auto_refresh_enabled',
        'news_auto_refresh_interval_seconds',
    ];

    /**
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'news_auto_refresh_enabled' => 'boolean',
            'news_auto_refresh_interval_seconds' => 'integer',
        ];
    }
}
