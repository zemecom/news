<?php

declare(strict_types=1);

namespace Modules\Delivery\Infrastructure\Persistence;

use Illuminate\Database\DatabaseManager;
use Modules\Delivery\Domain\Contracts\SourcePublicReader;

final readonly class EloquentSourcePublicReader implements SourcePublicReader
{
    public function __construct(private DatabaseManager $db) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listActive(): array
    {
        return $this->db->connection()
            ->table('sources')
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (object $row): array => [
                'id' => (int) $row->id,
                'name' => (string) $row->name,
            ])
            ->all();
    }
}
