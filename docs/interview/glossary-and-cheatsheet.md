# 16. Глоссарий и Быстрая Шпаргалка

Это файл для последних 5-10 минут перед собеседованием или перед обсуждением проекта с другим разработчиком.

## Модули за 30 секунд

| Модуль | Одной фразой |
| --- | --- |
| `Crawler` | Забирает и нормализует сырьё из RSS/Telegram |
| `Intelligence` | Прогоняет сырьё через pipeline и решает статус новости |
| `Catalog` | Хранит новости, источники и локальные медиа |
| `Delivery` | Отдаёт готовые данные наружу |
| `Shared` | Общие DTO, события, enum-ы и shared services |

## Главные DTO

| DTO | Где появляется | Для чего нужен |
| --- | --- | --- |
| `RawNewsData` | После `RawNewsFactory` | Стандартизированное сырьё |
| `EnrichedNewsData` | После pipeline | Финальная форма новости |
| `NewsFeedFilters` | В `NewsController@index` | Typed фильтры ленты |

## Главные события

| Событие | Кто создаёт | Кто слушает |
| --- | --- | --- |
| `RawNewsCreated` | `ProcessNewsJob` | `ProcessRawNewsListener` |
| `NewsEnriched` | `NewsProcessingPipeline` | `QueueMediaPreloadListener` |
| `SourceFetchSucceeded` | `FeedFetcherAction` | `UpdateSourceStatusListener` |
| `SourceFetchFailed` | `FeedFetcherAction` | `UpdateSourceStatusListener` |

## Главные очереди

| Очередь | Что в ней живёт |
| --- | --- |
| `crawler_tasks` | Fetch источников |
| `intelligence_tasks` | Обработка сырых новостей |
| `media_tasks` | Локальная загрузка медиа |

## Главные routing keys

| Key | Значение |
| --- | --- |
| `enriched.ready` | Готовая новость |
| `enriched.ready.important` | Важная готовая новость |
| `enriched.rejected` | Отклонённая новость |

## Главные таблицы

| Таблица | Что в ней главное |
| --- | --- |
| `sources` | настройки источника + health state |
| `news_items` | основная запись новости |
| `news_media_assets` | статусы и локальные пути медиа |
| `news_vectors` | задел под embeddings, сейчас не используется |

## Главные статусы новости

| Статус | Смысл |
| --- | --- |
| `processing` | raw запись уже есть, enrichment ещё не завершён |
| `published` | доступна Delivery и клиентам |
| `rejected` | отклонена модерацией/правилами |

## Главные классы, которые надо помнить

| Класс | Почему важен |
| --- | --- |
| `FeedFetcherAction` | сердце Crawler orchestration |
| `RawNewsFactory` | где сырьё превращается в DTO |
| `NewsProcessingPipeline` | сердце Intelligence |
| `DeduplicateStep` | первый важный step и момент raw insert |
| `EloquentNewsRepository` | хранение news в БД |
| `EloquentNewsFeedReader` | чтение ленты |
| `DbNewsMediaResolver` | подмена оригинальных media URL локальными |
| `SourceRuntimeHealthPolicy` | backoff логика источников |
| `FingerprintGenerator` | идемпотентность и дедуп |

## Маршруты, которые нужно помнить

| Route | Назначение |
| --- | --- |
| `GET /api/news` | лента |
| `GET /api/news/{id}` | карточка новости |
| `GET /api/sources` | публичный список источников |
| `GET /api/admin/sources` | admin список источников |
| `GET /health/live` | liveness |
| `GET /health/ready` | readiness |
| `GET /up` | built-in Laravel health route |
| `GET /` | web feed page |

## Команды, которые нужно помнить

| Команда | Назначение |
| --- | --- |
| `php artisan news:crawl` | запуск краулера |
| `php artisan news:media:backfill` | backfill локальных медиа |
| `php artisan news:messaging:setup` | создание RabbitMQ topology |
| `php artisan app:health-check` | CLI healthcheck |
| `php artisan octane:reload` | перезагрузка HTTP runtime |

## Если спросят X, открой Y

| Если спрашивают | Смотри |
| --- | --- |
| как идёт поток новости | [10. Сквозные Runtime-Сценарии](../architecture/10-end-to-end-flows.md) |
| как устроена БД | [11. Модель Данных, Таблицы и Индексы](../architecture/11-data-model-and-indexes.md) |
| чем jobs отличаются от events | [12. События, Очереди и Messaging](../architecture/12-events-queues-and-messaging.md) |
| как решена безопасность | [13. Безопасность, Отказы и Failure Modes](../architecture/13-security-and-failure-modes.md) |
| что тестируется и как | [14. Тестирование и Quality Gates](../architecture/14-testing-and-quality-gates.md) |
| где слабые места проекта | [17. Текущие Ограничения и Архитектурные Компромиссы](../architecture/17-known-limitations-and-tradeoffs.md) |

## 10 фраз, которые стоит уметь произнести без запинки

1. `Delivery` читает только `published` новости.
2. `ProcessNewsJob` не запускает pipeline напрямую, а публикует `RawNewsCreated`.
3. `DeduplicateStep` и DB unique index дают двойную защиту от дублей.
4. `NewsStore` — это shared boundary, а `NewsRepository` — Catalog-specific расширение.
5. `AntiClickbaitStep` сейчас фактически no-op.
6. `HeuristicTranslator` архитектурно на месте, но реально не переводит текст.
7. `SourceRuntimeHealthPolicy` рассчитывает backoff источников.
8. `news_vectors` уже в схеме, но пока не участвует в runtime.
9. RabbitMQ используется и как Laravel queue backend, и как AMQP exchange для внешних событий.
10. Проект — pragmatic modular monolith, а не "идеальный DDD-кристалл".

## Связанные документы

- [15. Банк Вопросов и Ответов для Собеседования](question-bank.md)
- [17. Текущие Ограничения и Архитектурные Компромиссы](../architecture/17-known-limitations-and-tradeoffs.md)
