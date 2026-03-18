<?php

declare(strict_types=1);

namespace Modules\Intelligence\Infrastructure\Codex;

use Modules\Intelligence\Domain\Exceptions\AiProviderRateLimitException as AiProviderRateLimitExceptionContract;
use RuntimeException;

final class CodexUsageLimitExceededException extends RuntimeException implements AiProviderRateLimitExceptionContract {}
