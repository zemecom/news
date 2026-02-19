<?php

declare(strict_types=1);

namespace Modules\Catalog\Infrastructure\Persistence;

use Illuminate\Support\Facades\DB;
use Modules\Catalog\Domain\Contracts\SourceRepository;
use Modules\Catalog\Infrastructure\Persistence\Models\Source;

final class EloquentSourceRepository implements SourceRepository
{
    public function updateSuccess(int $sourceId): void
    {
        Source::query()
            ->where('id', $sourceId)
            ->update([
                'last_success_at' => now(),
                'error_streak' => 0,
            ]);
    }

    public function updateFailure(int $sourceId): void
    {
        Source::query()
            ->where('id', $sourceId)
            ->update([
                'last_error_at' => now(),
                'error_streak' => DB::raw('error_streak + 1'),
            ]);
    }
}
