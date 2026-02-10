<?php

declare(strict_types=1);

namespace Modules\Intelligence\Domain\Contracts;

interface TitleGenerator
{
    public function generate(string $content, string $originalTitle): string;
}
