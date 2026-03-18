<?php

declare(strict_types=1);

namespace Modules\Intelligence\Infrastructure\Codex;

use Modules\Intelligence\Domain\Exceptions\AiProviderException as AiProviderExceptionContract;
use RuntimeException;

final class CodexException extends RuntimeException implements AiProviderExceptionContract {}
