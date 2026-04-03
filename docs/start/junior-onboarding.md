# Онбординг Для Джуна

Этот файл нужен для самого практического сценария: ты впервые открыл проект и хочешь не просто читать архитектурные документы, а руками поднять систему, увидеть данные и понять, где в коде что живёт.

## Что ты получишь в конце

После этого сценария у тебя должно получиться:

1. Поднять локальное окружение.
2. Увидеть живую ленту новостей, а не пустой экран.
3. Понять путь `HTTP -> Controller -> Action -> Reader -> JSON`.
4. Понять путь `crawl -> queue -> pipeline -> database -> delivery`.
5. Найти основные точки входа в коде без блуждания по репозиторию.

## 1. Подними окружение

Из корня проекта запусти на хосте:

```bash
make setup-local
make dev
```

Что это делает:

- `make setup-local` поднимает контейнеры, устанавливает зависимости, создаёт `.env`, генерирует ключ приложения, запускает миграции и `storage:link`;
- `make dev` запускает interactive development runtime внутри контейнера `app`.

После этого проверь:

- приложение: `http://localhost:8080`
- liveness: `http://localhost:8080/health/live`
- readiness: `http://localhost:8080/health/ready`
- Telescope: `http://localhost:8080/telescope`

## 2. Заполни проект данными для обучения

Это важный момент, на котором часто спотыкаются.

`make setup-local` поднимает инфраструктуру и миграции, но не создаёт demo-ленту новостей. Стандартный `DatabaseSeeder` сейчас добавляет пользователей и источники, а demo-news лежат в отдельном `NewsItemSeeder`.

Поэтому для первого знакомства выполни:

```bash
docker compose exec -T app php artisan db:seed --force
docker compose exec -T app php artisan db:seed --class=NewsItemSeeder --force
```

Что появится после этого:

- пользователи `admin@example.com` и `user@example.com` с паролем `password`;
- список источников;
- несколько demo-новостей для ленты.

Если ты не сделал этот шаг, `GET /api/news` может оказаться пустым, и это будет не баг API, а просто отсутствие данных.

## 3. Сделай первые проверки руками

### Проверка API

```bash
curl "http://localhost:8080/api/sources"
curl "http://localhost:8080/api/news?per_page=2"
curl "http://localhost:8080/api/news/1"
```

Что здесь важно увидеть:

- `/api/sources` возвращает публичный список активных источников;
- `/api/news` возвращает `data` и `meta`;
- `/api/news/{id}` возвращает одну новость или `404`, если записи нет.

### Проверка web-ленты

Открой:

- `http://localhost:8080/`

Это Blade-страница ленты. Она удобна для визуальной проверки и для знакомства с public delivery flow.

## 4. Проследи путь одного HTTP-запроса

Самый простой поток для старта:

`GET /api/news?per_page=2`

Иди по цепочке:

| Шаг | Файл | Что смотреть |
| --- | --- | --- |
| 1 | `routes/api.php` | какой route вызывает контроллер |
| 2 | `app/Http/Controllers/Api/NewsController.php` | как request превращается в DTO и JSON response |
| 3 | `app/Http/Requests/Api/NewsIndexRequest.php` | какие query-параметры валидируются |
| 4 | `src/Modules/Delivery/Domain/DTO/NewsFeedFilters.php` | typed shape фильтров |
| 5 | `src/Modules/Delivery/Application/Actions/ListNewsAction.php` | application use case без SQL |
| 6 | `src/Modules/Delivery/Infrastructure/Persistence/EloquentNewsFeedReader.php` | реальный SQL/read flow |
| 7 | `src/Modules/Delivery/Infrastructure/Persistence/DbNewsMediaResolver.php` | как локальные медиа подменяют оригинальные URL |

Если хочешь закрепить понимание, задай себе три вопроса:

1. Где заканчивается HTTP-layer и начинается модульная логика?
2. Где реально выполняется запрос к PostgreSQL?
3. Почему контроллер не должен собирать SQL сам?

## 5. Проследи путь одной новости от источника до ленты

Теперь посмотри на асинхронный сценарий.

Запусти вручную:

```bash
make crawl
```

Потом иди по цепочке:

| Шаг | Файл | Что смотреть |
| --- | --- | --- |
| 1 | `app/Console/Commands/NewsCrawlCommand.php` | кто выбирает источники |
| 2 | `src/Modules/Crawler/Application/Jobs/FetchSourceJob.php` | как source уходит в очередь |
| 3 | `src/Modules/Crawler/Application/Actions/FeedFetcherAction.php` | orchestration fetch flow |
| 4 | `src/Modules/Crawler/Application/Services/RawNewsFactory.php` | нормализация item -> `RawNewsData` |
| 5 | `src/Modules/Crawler/Infrastructure/Messaging/RawPublisher.php` | постановка raw-news в следующий этап |
| 6 | `src/Modules/Crawler/Application/Jobs/ProcessNewsJob.php` | transport job для сырой новости |
| 7 | `src/Modules/Intelligence/Application/Listeners/ProcessRawNewsListener.php` | старт pipeline |
| 8 | `src/Modules/Intelligence/Application/Pipeline/NewsProcessingPipeline.php` | порядок шагов enrichment |
| 9 | `src/Modules/Catalog/Infrastructure/Persistence/EloquentNewsRepository.php` | сохранение raw/enriched news |
| 10 | `src/Modules/Catalog/Application/Listeners/QueueMediaPreloadListener.php` | старт загрузки медиа |
| 11 | `src/Modules/Catalog/Application/Actions/PreloadNewsMediaAction.php` | локальное скачивание медиа |
| 12 | `src/Modules/Delivery/Infrastructure/Persistence/EloquentNewsFeedReader.php` | чтение уже опубликованной новости |

Самое полезное место для наблюдения за этим потоком локально:

- Telescope: jobs, exceptions, logs;
- `make logs`;
- `GET /api/news`;
- таблицы `news_items` и `news_media_assets`.

## 6. Что делать, если что-то пошло не так

### Лента пустая

Проверь по порядку:

1. Выполнял ли ты `db:seed --force`.
2. Выполнял ли ты `db:seed --class=NewsItemSeeder --force`.
3. Есть ли записи в `sources`.
4. Есть ли записи в `news_items`.
5. Возвращает ли `/api/news` HTTP `200`.

### `/health/ready` возвращает `503`

Обычно это значит, что одна из зависимостей недоступна:

- PostgreSQL;
- Redis;
- RabbitMQ.

Смотри:

```bash
make logs
```

и Telescope.

### Ты изменил код, но поведение не поменялось

Проект работает через Octane/RoadRunner, поэтому код живёт в long-lived процессе.

Перед ручной HTTP-проверкой выполни:

```bash
docker compose exec -T app php artisan octane:reload
```

### Jobs не исполняются

Проверь:

1. работает ли контейнер `worker`;
2. нет ли ошибок в RabbitMQ;
3. видны ли jobs в Telescope;
4. не упали ли listeners/pipeline steps.

## 7. Какие документы читать дальше

Если ты уже поднял проект и увидел данные, дальше лучше идти так:

1. [News API: запросы и ответы](../reference/api/news-api.md) — чтобы понимать shape API и query-параметры.
2. [Runtime и операционные процессы](../guides/runtime-operations.md) — чтобы понимать, кто что реально запускает.
3. [Конфигурация окружения](../reference/config/env.md) — чтобы не ломать окружение случайными правками `.env`.
4. [Обзор документации и карта проекта](../architecture/00-overview.md) — чтобы перейти от практики к архитектурной карте.
5. [Учебный маршрут по проекту](learning-path.md) — чтобы учиться на проекте постепенно, а не хаотично.

## 8. Что важно понять про этот проект как про учебную базу

Это не маленький CRUD-проект. Здесь специально полезно учиться на связях между частями системы:

- HTTP и DTO;
- очереди и jobs;
- события и listeners;
- persistence и read-model;
- Docker runtime и Octane;
- тесты как защита архитектуры.

Если сначала кажется, что частей слишком много, это нормально. Правильная стратегия здесь не "понять всё сразу", а несколько раз пройти два маршрута:

1. `request -> response`;
2. `source -> queue -> pipeline -> published news`.
