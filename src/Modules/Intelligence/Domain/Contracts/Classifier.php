<?php

declare(strict_types=1);

namespace Modules\Intelligence\Domain\Contracts;

interface Classifier
{
    /**
     * @return array{category:string,tags:array}
     */
    public function classify(string $content): array;
}
