<?php

declare(strict_types=1);

namespace Modules\Intelligence\Application\Pipeline;

/**
 * Thrown when a pipeline step determines that the message
 * should be silently skipped (e.g. duplicate detection).
 */
final class SkipMessageException extends \RuntimeException
{
    public function __construct(string $reason = 'skipped')
    {
        parent::__construct($reason);
    }
}
