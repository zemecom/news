# Learning Path

Этот файл нужен не для чтения "по диагонали", а для обучения на проекте. Ниже путь из небольших практических задач: от самого простого наблюдения за системой до аккуратных изменений в коде.

## Как им пользоваться

1. Делай задания по порядку.
2. После каждого шага смотри код и тесты.
3. Не пытайся понять все модули сразу.
4. Если шаг кажется слишком большим, дроби его ещё на два маленьких шага.

## Шаг 1. Просто подними проект и получи первый JSON

### Цель

Понять, что проект вообще работает локально.

### Сделай

1. Выполни сценарий из [junior-onboarding.md](junior-onboarding.md).
2. Открой `GET /api/news`.
3. Открой `GET /api/sources`.
4. Открой `/`.

### Чему ты учишься

- не бояться большого репозитория;
- видеть различие между API и web surface;
- понимать, что сначала нужен живой runtime, а потом уже архитектурные рассуждения.

## Шаг 2. Найди путь одного запроса до SQL

### Цель

Перестать воспринимать backend как "магический Laravel".

### Сделай

Пройди руками маршрут:

1. `routes/api.php`
2. `NewsController@index`
3. `NewsIndexRequest`
4. `NewsFeedFilters`
5. `ListNewsAction`
6. `EloquentNewsFeedReader`

### Проверь себя

Ответь письменно или устно:

1. Где заканчивается transport-layer?
2. Где начинается application use case?
3. Где реально происходит чтение из PostgreSQL?

## Шаг 3. Разбери одну новость от crawler до публикации

### Цель

Понять асинхронную часть системы.

### Сделай

1. Запусти `make crawl`.
2. Открой Telescope.
3. Найди цепочку job -> listener -> pipeline.
4. Посмотри, как новость попадает в `news_items`.

### Смотри в коде

- `NewsCrawlCommand`
- `FetchSourceJob`
- `FeedFetcherAction`
- `RawNewsFactory`
- `ProcessNewsJob`
- `ProcessRawNewsListener`
- `NewsProcessingPipeline`

### Чему ты учишься

- отличать jobs от событий;
- понимать, зачем нужна дедупликация в нескольких местах;
- видеть, что данные не появляются в ленте мгновенно "в одном запросе".

## Шаг 4. Разбери тесты как документацию поведения

### Цель

Научиться использовать тесты не только для проверки, но и для чтения контракта.

### Сделай

Прочитай:

- `tests/Feature/Api/NewsApiTest.php`
- `tests/Feature/Acceptance/NewsFlowAcceptanceTest.php`
- `tests/Architecture/LayerDependenciesTest.php`

### Ответь себе

1. Какие JSON-поля API обещает наружу?
2. Какие сценарии доступа админки считаются обязательными?
3. Какие архитектурные ограничения реально защищены тестами?

## Шаг 5. Сделай самое маленькое безопасное изменение

### Цель

Почувствовать проект руками без высокого риска всё сломать.

### Хорошие первые задачи

1. Добавить или уточнить документацию рядом с уже существующим поведением.
2. Улучшить текст ошибки или help-текст команды.
3. Добавить/уточнить тест под уже существующий сценарий.
4. Поправить валидацию несложного параметра запроса.

### Почему это хороший этап

Ты учишься вносить изменения без попытки сразу "рефакторить архитектуру целиком".

## Шаг 6. Сделай первую функциональную доработку в Delivery

### Цель

Научиться вносить изменения в самый понятный модуль.

### Хорошие примеры

1. Добавить новый фильтр в ленту.
2. Добавить новое поле в public response shape.
3. Уточнить сортировку или mapping.

### Обычно это затрагивает

- `NewsIndexRequest`
- `NewsFeedFilters`
- `ListNewsAction`
- `EloquentNewsFeedReader`
- API tests

### Что читать рядом

- [03-delivery.md](../architecture/03-delivery.md)
- [adding-a-feature.md](../guides/adding-a-feature.md)
- [news-api.md](../reference/api/news-api.md)

## Шаг 7. Попробуй задачу среднего уровня в Crawler

### Цель

Понять внешние интеграции и защиту от "грязного интернета".

### Примеры

1. Добавить поддержку нового RSS parser-а.
2. Ужесточить правило sanitization.
3. Добавить тест на SSRF/security policy.

### Чему ты учишься

- как проект работает с недоверенным вводом;
- где живут adapters и policies;
- как не смешивать parsing с delivery.

## Шаг 8. Разберись в Intelligence без романтизации

### Цель

Понять, как устроен pipeline, и не ожидать от него того, чего в нём пока нет.

### На что смотреть

- порядок шагов в `NewsProcessingPipeline`;
- `DeduplicateStep`;
- `ModerationStep`;
- `FinalizeStep`;
- heuristic adapters.

### Что важно

Этот модуль нужен не для демонстрации "магического AI", а для понимания:

- orchestration;
- boundary contracts;
- enrichment pipeline;
- постепенной эволюции архитектуры.

## Шаг 9. Научись диагностировать проект как систему

### Цель

Перейти от уровня "я читаю код" к уровню "я понимаю поведение runtime".

### Практика

1. Проверь `/health/live` и `/health/ready`.
2. Открой Telescope.
3. Запусти `make logs`.
4. Посмотри admin-источники.
5. Разберись, где scheduler описан, а где реально запускается runtime.

### Что читать

- [runtime-operations.md](../guides/runtime-operations.md)
- [07-admin-and-debugging.md](../architecture/07-admin-and-debugging.md)
- [08-infrastructure.md](../architecture/08-infrastructure.md)

## Шаг 10. Только после этого лезь в большие изменения

Когда уже стоит трогать более серьёзные вещи:

- новые pipeline steps;
- новые источники;
- schema changes;
- messaging contracts;
- infra behavior.

Перед этим шагом желательно уже понимать:

1. как устроен `Delivery`;
2. как проходит новость через crawler/pipeline;
3. как проект проверяется тестами;
4. почему Octane меняет правила локальной проверки.

## Хороший порядок чтения рядом с практикой

1. [junior-onboarding.md](junior-onboarding.md)
2. [news-api.md](../reference/api/news-api.md)
3. [runtime-operations.md](../guides/runtime-operations.md)
4. [00-overview.md](../architecture/00-overview.md)
5. [03-delivery.md](../architecture/03-delivery.md)
6. [04-crawler.md](../architecture/04-crawler.md)
7. [05-intelligence.md](../architecture/05-intelligence.md)
8. [adding-a-feature.md](../guides/adding-a-feature.md)

## Что считать хорошим прогрессом

Ты движешься правильно, если уже можешь спокойно ответить на такие вопросы:

1. Почему `GET /api/news` не парсит RSS в момент запроса?
2. Где в проекте реально живёт SQL для ленты?
3. Чем job отличается от domain event в этом проекте?
4. Почему после изменения кода иногда нужен `octane:reload`?
5. Почему у проекта есть и `worker`, и `app`?
