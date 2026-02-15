<?php

declare(strict_types=1);

namespace Modules\Intelligence\Infrastructure\LLM;

use Modules\Intelligence\Domain\Contracts\Classifier;

final class KeywordClassifier implements Classifier
{
    /**
     * @var array<string, array{category:string,tags:array<int,string>}>
     */
    private array $map = [
        'laravel' => ['category' => 'IT', 'tags' => ['it', 'laravel', 'php']],
        'php' => ['category' => 'IT', 'tags' => ['it', 'php']],
        'ai' => ['category' => 'IT', 'tags' => ['it', 'ai']],
        'econom' => ['category' => 'Экономика', 'tags' => ['economy']],
        'market' => ['category' => 'Экономика', 'tags' => ['markets']],
        'polit' => ['category' => 'Политика', 'tags' => ['politics']],
        'crime' => ['category' => 'Криминал', 'tags' => ['crime']],
        'медицин' => ['category' => 'Медицина', 'tags' => ['medicine']],
        'кримин' => ['category' => 'Криминал', 'tags' => ['crime']],
    ];

    public function classify(string $content): array
    {
        $normalized = mb_strtolower($content);

        foreach ($this->map as $needle => $result) {
            if (str_contains($normalized, $needle)) {
                return $result;
            }
        }

        return [
            'category' => 'IT',
            'tags' => ['it'],
        ];
    }
}
