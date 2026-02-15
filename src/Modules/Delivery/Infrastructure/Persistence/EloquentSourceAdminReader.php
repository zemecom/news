<?php

declare(strict_types=1);

namespace Modules\Delivery\Infrastructure\Persistence;

use Illuminate\Database\DatabaseManager;
use Modules\Delivery\Domain\Contracts\SourceAdminReader;

final class EloquentSourceAdminReader implements SourceAdminReader
{
    public function __construct(private DatabaseManager $db) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function list(): array
    {
        return $this->db->connection()
            ->table('sources')
            ->orderBy('id')
            ->get(
                columns: [
                    'id',
                    'name',
                    'url',
                    'type',
                    'language_default',
                    'cron_expression',
                    'is_active',
                    'last_success_at',
                    'last_error_at',
                    'error_streak',
                ],
            )
            ->map(fn (object $source): array => $this->mapSourceRow($source))
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    private function mapSourceRow(object $source): array
    {
        /** @var array<string, mixed> $sourceData */
        $sourceData = get_object_vars($source);

        return [
            'id' => (int) ($sourceData['id'] ?? 0),
            'name' => (string) ($sourceData['name'] ?? ''),
            'url' => (string) ($sourceData['url'] ?? ''),
            'type' => (string) ($sourceData['type'] ?? ''),
            'language_default' => $sourceData['language_default'] ?? null,
            'cron_expression' => $sourceData['cron_expression'] ?? null,
            'is_active' => (bool) ($sourceData['is_active'] ?? false),
            'last_success_at' => isset($sourceData['last_success_at']) ? (string) $sourceData['last_success_at'] : null,
            'last_error_at' => isset($sourceData['last_error_at']) ? (string) $sourceData['last_error_at'] : null,
            'error_streak' => (int) ($sourceData['error_streak'] ?? 0),
        ];
    }
}
