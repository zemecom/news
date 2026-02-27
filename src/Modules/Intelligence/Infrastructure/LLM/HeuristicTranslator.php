<?php

declare(strict_types=1);

namespace Modules\Intelligence\Infrastructure\LLM;

use Modules\Intelligence\Domain\Contracts\Translator;

/**
 * Базовая эвристическая "заглушка" для перевода.
 * Модуль: Intelligence. Слой: Infrastructure.
 *
 * В реальном приложении здесь будет адаптер к DeepL, Google Translate или локальной нейросети.
 * Класс реализует доменный контракт `Translator`, скрывая детали запросов к внешнему API.
 */
final class HeuristicTranslator implements Translator
{
    public function translate(string $text, string $targetLanguage, string $sourceLanguage): string
    {
        return $text;
    }
}
