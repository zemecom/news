# 14. Тестирование и Quality Gates

Этот файл нужен, чтобы понимать, как проект страхует своё поведение и архитектуру. На собеседовании это часто отдельный сильный блок: умение объяснить не только "как работает", но и "как ты убеждаешься, что не сломал систему".

## Зачем существует эта часть системы

В проекте есть несколько уровней защиты:

1. Unit tests.
2. Feature/acceptance tests.
3. Architecture tests.
4. Статический анализ.
5. Линтинг и quality commands в Makefile.

## Карта тестов

### Unit

| Файл | Что проверяет |
| --- | --- |
| `tests/Unit/Crawler/FeedFetcherActionTest.php` | Telegram fetch path, unsupported source type, события успеха/ошибки |
| `tests/Unit/Crawler/IncomingContentSanitizerTest.php` | Очистку контента и URL |
| `tests/Unit/Crawler/SourceUrlPolicyTest.php` | Allowlist/scheme/private host rules |
| `tests/Unit/Shared/FingerprintGeneratorTest.php` | Детерминированность fingerprint |

### Feature

| Файл | Что проверяет |
| --- | --- |
| `tests/Feature/Api/NewsApiTest.php` | JSON shape ленты, фильтры, details, sources |
| `tests/Feature/Api/AdminSourcesApiTest.php` | Доступ admin API |
| `tests/Feature/Console/NewsCrawlCommandTest.php` | Backoff logic в команде краулинга |
| `tests/Feature/Console/NewsMediaBackfillCommandTest.php` | Backfill сценарии по медиа |
| `tests/Feature/Crawler/ParserIntegrationTest.php` | Интеграция парсеров |
| `tests/Feature/Web/FeedPageTest.php` | Доступность web feed page |

### Acceptance

| Файл | Что проверяет |
| --- | --- |
| `tests/Feature/Acceptance/NewsFlowAcceptanceTest.php` | Сквозной сценарий чтения ленты и прав admin-доступа |

### Architecture

| Файл | Что проверяет |
| --- | --- |
| `tests/Architecture/LayerDependenciesTest.php` | Границы слоёв и модулей |
| `tests/Architecture/CodeQualityTest.php` | strict types, запрет debug-call-ов, final-by-default policy |

## Что именно проверяют arch tests

### `LayerDependenciesTest`

Проверяет:

1. Контроллеры не используют `App\Models` напрямую.
2. В `App` нельзя использовать `Http` facade.
3. Модули не должны тащить друг друга в `Domain` и `Application`.
4. `Domain` не зависит от `Application` и `Infrastructure`.
5. `Application` не зависит от `Infrastructure`.
6. Controllers не должны использовать DB/Cache/Http/Redis/Model/Builder напрямую.

Это один из главных реальных инструментов защиты modular boundaries.

### `CodeQualityTest`

Проверяет:

1. `strict_types`;
2. отсутствие `dd`, `dump`, `ray`, `die`, `echo`, `print_r`, `var_dump`;
3. final-by-default policy, за вычетом разрешённых категорий.

## Quality commands из Makefile

| Команда | Смысл |
| --- | --- |
| `make test` | unit/feature tests |
| `make test-coverage` | unit/feature tests + pcov coverage gate |
| `make test-arch` | архитектурные тесты |
| `make acceptance` | acceptance suite |
| `make ci-check` | validate + audit + lint-check + analyze + psalm-taint + coverage gate + arch |
| `make agent-check` | reload + lint + docs-deps + analyze + full test-all |

GitHub CI разделяет эти контуры:

- `push` / `pull_request`: только детерминированные quality gates.
- `schedule` / `workflow_dispatch`: acceptance suite, включая внешние smoke-check сценарии.

## Статический анализ и стиль

### Инструменты

| Инструмент | Роль |
| --- | --- |
| `PHPStan` | Типы и потенциальные ошибки |
| `Psalm` | Более строгая статическая проверка, включая taint mode |
| `Pint` | Coding style |
| `Rector` | Автоматизированные рефакторинги |

### Почему это важно

Проект строится на довольно сложных границах между DTO, очередями, listeners и persistence. Ошибка типа или неправильно понятый shape данных может привести к runtime-багу не сразу, а в асинхронном потоке. Поэтому статический анализ здесь особенно ценен.

## Примеры того, что реально страхуют тесты

### `NewsApiTest`

Страхует:

- shape ответа;
- работу фильтров;
- `404` на неизвестную новость;
- список публичных источников.

### `NewsCrawlCommandTest`

Страхует:

- пропуск источников в backoff window;
- override через `--ignore-backoff`.

### `NewsMediaBackfillCommandTest`

Страхует:

- что по умолчанию backfill не трогает уже обработанные assets;
- что `--all` переобрабатывает всё.

### `NewsFlowAcceptanceTest`

Страхует:

- базовый путь пользователя от открытия feed до details;
- admin access control.

### `ParserIntegrationTest`

Страхует:

- что реальные внешние RSS feed-ы вообще можно скачать и распарсить;
- что `RssParserResolver` выбирает parser и тот возвращает ожидаемый набор ключей.

Важно: этот тест может быть помечен как skipped из-за сетевых условий, поэтому он полезен как интеграционный smoke-check, но не равен полностью детерминированному unit test. По этой причине он не должен блокировать обычный PR/push quality gate и вынесен в отдельный CI-контур.

## Что тесты пока не доказывают полностью

Это тоже важно уметь проговаривать.

1. Полноценную корректность реального AI behavior, потому что AI adapters сейчас эвристические.
2. Реальную внешнюю доступность Telegram/RSS в продовых условиях.
3. Полную корректность внешних AMQP consumers.
4. Нагрузочное поведение и latency under pressure.

## Как думать о новых тестах

Если ты добавляешь:

| Тип изменения | Минимальный тест |
| --- | --- |
| Новый filter/read shape | API feature test |
| Новый parser/security rule | Unit/feature test Crawler |
| Новый pipeline step | Unit/feature test Intelligence |
| Новое межмодульное событие | Feature test event reaction |
| Новую boundary rule | Architecture test |

## Что могут спросить на собеседовании

### Вопрос
Чем полезны architecture tests в modular monolith?

**Короткий ответ:** они не дают модулям и слоям незаметно деградировать в связную кашу.

### Вопрос
Почему в проекте важен и unit, и feature, и acceptance уровень?

**Короткий ответ:** потому что здесь есть и локальные алгоритмы, и сложные сквозные потоки.

### Вопрос
Что не покрыто тестами полностью?

**Короткий ответ:** реальные внешние интеграции и production-like AI/AMQP поведение.

## Куда смотреть в коде

- `tests/Architecture/LayerDependenciesTest.php`
- `tests/Architecture/CodeQualityTest.php`
- `tests/Feature/Api/NewsApiTest.php`
- `tests/Feature/Acceptance/NewsFlowAcceptanceTest.php`
- `tests/Feature/Console/NewsCrawlCommandTest.php`
- `tests/Feature/Console/NewsMediaBackfillCommandTest.php`
- `tests/Unit/Crawler/FeedFetcherActionTest.php`
- `Makefile`

## Связанные документы

- [09. Как Добавлять Новую Фичу в Этот Проект](../guides/adding-a-feature.md)
- [17. Текущие Ограничения и Архитектурные Компромиссы](17-known-limitations-and-tradeoffs.md)
