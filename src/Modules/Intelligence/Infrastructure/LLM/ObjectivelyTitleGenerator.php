<?php

declare(strict_types=1);

namespace Modules\Intelligence\Infrastructure\LLM;

use Modules\Intelligence\Domain\Contracts\TitleGenerator;

final class ObjectivelyTitleGenerator implements TitleGenerator
{
    /** @var array<int, string> */
    private array $clickbaitTokens = [
        'шок',
        'сенсац',
        'не поверите',
        '!!!',
        'срочно',
        'breaking',
    ];

    public function generate(string $content, string $originalTitle): string
    {
        $title = trim($originalTitle);
        $normalizedTitle = mb_strtolower($title);

        foreach ($this->clickbaitTokens as $token) {
            if (str_contains($normalizedTitle, $token)) {
                $title = '';
                break;
            }
        }

        if ($title !== '') {
            return $this->truncate($title);
        }

        $content = trim(preg_replace('/\s+/', ' ', strip_tags($content)) ?? '');
        if ($content === '') {
            return 'Новость без заголовка';
        }

        return $this->truncate($content);
    }

    private function truncate(string $value, int $limit = 140): string
    {
        if (mb_strlen($value) <= $limit) {
            return $value;
        }

        return rtrim(mb_substr($value, 0, $limit - 1)).'…';
    }
}
