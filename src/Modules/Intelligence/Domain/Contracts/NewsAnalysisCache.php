<?php

declare(strict_types=1);

namespace Modules\Intelligence\Domain\Contracts;

use Modules\Intelligence\Domain\DTO\NewsAnalysisResult;

interface NewsAnalysisCache
{
    public function get(
        string $fingerprint,
        string $provider,
        string $model,
        string $reasoningEffort,
        int $analysisVersion,
    ): ?NewsAnalysisResult;

    public function put(
        string $fingerprint,
        string $provider,
        string $model,
        string $reasoningEffort,
        int $analysisVersion,
        NewsAnalysisResult $result,
    ): void;
}
