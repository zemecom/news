<?php

declare(strict_types=1);

namespace App\Services\Contracts;

interface QueuePreviewClient
{
    /**
     * @return list<array<string, mixed>>
     */
    public function previewQueue(string $queueName, int $limit = 10): array;
}
