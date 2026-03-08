# 13. Безопасность, Отказы и Failure Modes

Этот файл отвечает на вопрос: "Что произойдёт, если всё пойдёт не по плану?". Для production-minded backend-разработчика это не менее важно, чем happy path.

## Зачем существует эта часть системы

Проект работает с:

- внешними URL;
- сырой HTML/XML разметкой;
- очередями;
- локальным файловым storage;
- long-lived процессами.

Каждый из этих контуров несёт свой класс рисков.

## Основные классы рисков

| Класс риска | Где проявляется |
| --- | --- |
| SSRF | внешние URL источников |
| XSS/грязный контент | HTML/XML из RSS и Telegram |
| Дубли и race conditions | параллельный ingestion |
| Залипшие источники | повторяющиеся ошибки fetch |
| Частичный успех | новость сохранена, медиа не скачано |
| Long-lived state bugs | Octane и queue workers |

---

## SSRF hardening

### Где реализовано

`src/Modules/Crawler/Infrastructure/Security/SourceUrlPolicy.php`

### Что проверяется

1. Разрешённая схема source URL.
2. Разрешённый host по allowlist.
3. Запрет на private/reserved hosts.

### Конфигурация

`config/crawler.php`:

- `allowlist.global`
- `allowlist.rss`
- `allowlist.telegram`
- `security.allowed_source_schemes`
- `security.deny_private_hosts`

### Какие атаки это снижает

- обращения к `localhost`;
- обращения в private network;
- попытки использовать crawler как внутренний proxy к сервисам инфраструктуры.

---

## Sanitization и нормализация контента

### Где реализовано

`IncomingContentSanitizer`

### Что очищается

| Тип данных | Что делается |
| --- | --- |
| текст | удаление опасных HTML блоков, strip_tags, нормализация whitespace |
| URL | проверка схемы и структуры |
| categories | очистка, ограничение длины, дедуп |
| links | фильтрация по допустимым URL |
| media | фильтрация по URL и допустимому mime-like type |

### Что это не делает

Sanitizer не является универсальным HTML sanitizer уровня "рендерить rich HTML как есть". Он скорее переводит входящий контент в безопасный текстовый/структурированный формат для дальнейшего хранения и обработки.

---

## Дедупликация и идемпотентность

### Первый слой

`Crawler` проверяет fingerprint через `Deduplicator`.

Цель:

- не плодить лишние jobs;
- снизить нагрузку downstream.

### Второй слой

`DeduplicateStep` в Intelligence вызывает `NewsStore::existsByFingerprint()` и затем `storeRaw()`.

### Третий слой

DB unique constraint по `raw_fingerprint`.

### Почему это важно

Повторная доставка сообщений, concurrency и баги в внешних источниках — это нормальная реальность. Проект защищается сразу на нескольких уровнях.

---

## Backoff источников

### Где реализовано

- `SourceRuntimeHealthPolicy`
- `EloquentSourceRepository`
- `NewsCrawlCommand`

### Как работает

1. `FeedFetcherAction` публикует `SourceFetchFailed`.
2. Listener обновляет `error_streak` и `retry_backoff_state`.
3. Следующий `news:crawl` вычисляет `nextRetryAt`.
4. Пока backoff активен, источник пропускается.

### Почему это полезно

- не тратятся ресурсы на мёртвые источники;
- логика не завязана только на cron/scheduler;
- source health становится частью runtime decision-making.

---

## Partial failure: новость есть, медиа нет

Это один из ключевых проектных failure modes.

### Что происходит

1. Новость может быть успешно сохранена и опубликована.
2. `NewsEnriched` запускает `PreloadNewsMediaJob`.
3. Скачивание файла может упасть.
4. В `news_media_assets` запись получает `failed`.
5. Delivery при этом всё равно отдаст исходный внешний URL как fallback.

### Почему это хорошее поведение

Пользователь видит новость даже при проблеме с локализацией медиа. Failure isolated to media layer, а не ломает весь flow.

---

## Очереди и retry failures

### Где могут быть падения

| Этап | Тип ошибки |
| --- | --- |
| `FetchSourceJob` | сеть, парсинг, invalid source type |
| `ProcessNewsJob` | маловероятно, в основном transport-layer ошибка |
| `ProcessRawNewsListener` | pipeline exception |
| `PreloadNewsMediaJob` | HTTP download/storage error |

### Что важно

- `SkipMessageException` не считается аварией: это controlled stop.
- реальные exceptions должны быть видны в logs/Telescope/failed jobs.

---

## Long-lived process risks

### Где это важно

- `app` контейнер под Octane
- `worker` контейнер под queue:work

### Классическая опасность

Сервис случайно хранит mutable state в singleton/static property, и следующий запрос или job получает загрязнённое состояние.

### Что проект делает правильно

Большинство сервисов stateless или readonly-oriented. Но риск всё равно системный и его нужно помнить при любом новом коде.

---

## Security и delivery layer

Важно понимать: Delivery не рендерит сырой HTML-blob как rich content. Он отдаёт уже очищенные значения из storage/read-model, что уменьшает риск XSS-like отражения пользовательского контента.

---

## Security и admin layer

### Что есть

- auth middleware;
- role check;
- Filament login;
- локальный auto-login только в local env.

### Чего нет в этом проекте как центра темы

- сложной RBAC-модели;
- permission matrix;
- multi-tenant isolation;
- audit trail административных действий.

Это нужно честно признавать как ограничение текущего scope.

## Типовые аварийные сценарии

### Сценарий 1. Telegram поменял HTML

Симптомы:

- crawler возвращает 0 items;
- логика пагинации перестаёт работать;
- parser tests/интеграционные проверки падают.

### Сценарий 2. Источник недоступен неделями

Симптомы:

- растёт `error_streak`;
- выставляется backoff;
- source перестаёт участвовать в новых fetch cycles.

### Сценарий 3. Локальный storage недоступен

Симптомы:

- `PreloadNewsMediaAction` маркирует asset как `failed`;
- клиент продолжает получать original URL.

### Сценарий 4. Повторно пришёл тот же item

Симптомы:

- ранний dedupe или DB uniqueness не дают создать дубликат.

## Что могут спросить на собеседовании

### Вопрос
Как проект защищается от SSRF?

**Короткий ответ:** через `SourceUrlPolicy`, allowlist и запрет private hosts.

### Вопрос
Как проект переживает повторную доставку одной и той же новости?

**Короткий ответ:** через fingerprint, многоуровневую дедупликацию и unique constraint.

### Вопрос
Что будет, если картинка не скачалась?

**Короткий ответ:** новость останется доступной, а Delivery вернёт fallback на оригинальный URL.

## Куда смотреть в коде

- `config/crawler.php`
- `src/Modules/Crawler/Infrastructure/Security/SourceUrlPolicy.php`
- `src/Modules/Crawler/Application/Services/IncomingContentSanitizer.php`
- `src/Modules/Shared/Application/Services/FingerprintGenerator.php`
- `src/Modules/Shared/Application/Services/SourceRuntimeHealthPolicy.php`
- `src/Modules/Catalog/Application/Actions/PreloadNewsMediaAction.php`
- `src/Modules/Catalog/Infrastructure/Persistence/EloquentSourceRepository.php`

## Связанные документы

- [04. Модуль Crawler: Получение Сырого Контента](04-crawler.md)
- [06. Модули Catalog и Shared: Хранение, Контракты и Межмодульные События](06-catalog-shared.md)
- [17. Текущие Ограничения и Архитектурные Компромиссы](17-known-limitations-and-tradeoffs.md)
