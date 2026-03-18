<?php

declare(strict_types=1);

namespace Modules\Intelligence\Domain\Contracts;

use Modules\Intelligence\Domain\DTO\AiProviderProfile;
use Modules\Intelligence\Domain\DTO\NewsAnalysisResult;
use Modules\Shared\Domain\DTO\RawNewsData;

interface NewsAnalyzer
{
    public function analyze(RawNewsData $raw, AiProviderProfile $account): NewsAnalysisResult;
}
