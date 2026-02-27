<?php

declare(strict_types=1);

namespace Modules\Catalog\Infrastructure\Persistence;

use Illuminate\Support\Facades\DB;
use Modules\Catalog\Domain\Contracts\SourceRepository;
use Modules\Catalog\Infrastructure\Persistence\Models\Source;
use Modules\Shared\Application\Services\SourceRuntimeHealthPolicy;

final readonly class EloquentSourceRepository implements SourceRepository
{
    public function __construct(private SourceRuntimeHealthPolicy $runtimeHealthPolicy) {}

    public function updateSuccess(int $sourceId): void
    {
        Source::query()
            ->where('id', $sourceId)
            ->update([
                'last_success_at' => now(),
                'error_streak' => 0,
                'retry_backoff_state' => null,
            ]);
    }

    public function updateFailure(int $sourceId): void
    {
        DB::transaction(function () use ($sourceId): void {
            /** @var Source|null $source */
            $source = Source::query()
                ->where('id', $sourceId)
                ->lockForUpdate()
                ->first(['id', 'error_streak']);

            if ($source === null) {
                return;
            }

            $failedAt = now();
            $nextErrorStreak = min(65535, ((int) $source->getAttribute('error_streak')) + 1);
            $backoffState = $this->runtimeHealthPolicy->buildFailureBackoffState($nextErrorStreak, $failedAt);

            Source::query()
                ->where('id', $sourceId)
                ->update([
                    'last_error_at' => $failedAt,
                    'error_streak' => $nextErrorStreak,
                    'retry_backoff_state' => $backoffState,
                ]);
        });
    }
}
