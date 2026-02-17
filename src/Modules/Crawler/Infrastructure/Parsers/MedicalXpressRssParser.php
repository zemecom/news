<?php

declare(strict_types=1);

namespace Modules\Crawler\Infrastructure\Parsers;

class MedicalXpressRssParser extends DefaultRssParser
{
    #[\Override]
    public function supports(string $url): bool
    {
        return str_contains($url, 'medicalxpress.com');
    }
}
