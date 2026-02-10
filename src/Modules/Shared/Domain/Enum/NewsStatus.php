<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Enum;

enum NewsStatus: string
{
    case PROCESSING = 'processing';
    case PUBLISHED = 'published';
    case REJECTED = 'rejected';
}
