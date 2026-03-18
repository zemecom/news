<?php

declare(strict_types=1);

namespace Modules\Intelligence\Infrastructure\Codex;

use Modules\Intelligence\Domain\Exceptions\AiProviderUnauthorizedException as AiProviderUnauthorizedExceptionContract;
use RuntimeException;

final class CodexUnauthorizedException extends RuntimeException implements AiProviderUnauthorizedExceptionContract {}
