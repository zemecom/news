# PROJECT_MEMORY

## 1) Контекст проекта

- Проект: **SmartNews Aggregator** (Laravel 12, PHP 8.5, Modular Monolith).
- Цель: сбор, обработка и доставка новостей с AI-обогащением, с готовностью к запуску в Docker/Kubernetes.
- Текущее состояние: рабочий backend-контур + web-лента + Telegram crawler + очереди RabbitMQ.
- Основной URL в локальной среде: `http://localhost:8080`.
- Корень `src` зарезервирован под три верхнеуровневые зоны: `Modules/` (бизнес-модули и shared kernel), `Support/` (общие технические абстракции), `Infrastructure/` (общая инфраструктурная обвязка для нескольких модулей).
- Карта интерфейсов классов: `docs/PROJECT_INTERFACE.md`.
- Карта структуры проекта: `docs/PROJECT_STRUCTURE.md`.

---

## 2) Что реализовано по ТЗ (детально)

## 2.1 Технологический стек

- **Backend/архитектура**
    - Laravel 12 + strict types.
    - Modular Monolith структура: `src/Modules/{Crawler,Intelligence,Catalog,Delivery,Shared}`.
    - Контейнер модулей подключается через `app/Providers/ModulesServiceProvider.php`.
    - **Высокопроизводительный сервер**: Laravel Octane + RoadRunner (заменяет классическую связку Nginx + PHP-FPM).
- **Интеграции**
    - Saloon используется для HTTP-клиентов (RSS/Telegram web endpoint).
    - RabbitMQ:
        - как драйвер Laravel Queue (`vladimir-yuldashev/laravel-queue-rabbitmq`) для внутренних job-очередей,
        - как AMQP transport (`php-amqplib`) для exchange `news_flow` и событий доставки.
- **Data**
    - PostgreSQL + JSONB-поля в `sources/news_items`.
    - Redis подключен для инфраструктурного контура.
- **Code quality & Debugging**
    - Pest + Arch tests, PHPStan, Psalm (в т.ч. taint), Pint, Rector.
    - **Laravel Telescope**: установлен для отладки в локальной среде (запросы, очереди, события).
    - Автоматический запуск `make ci-check` через `pre-commit` hook перед коммитом изменений.

## 2.2 Архитектура модулей

### 2.2.1 Архитектурный стиль (Hexagonal Architecture)

Проект следует принципам **Hexagonal Architecture (Ports and Adapters)** внутри каждого модуля Модульного Молита.
Правила жестко зафиксированы в `deptrac.yaml` и проверяются в CI.

**Слои (от центра к периферии):**

1.  **Domain** (`src/Modules/*/Domain`):
    - Ядро бизнес-логики.
    - Зависит **только** от `Shared` (Kernel).
    - Запрещено использование классов из `Application`, `Infrastructure`, `App`.
    - Содержит: Entities, Value Objects, Domain Services, Repository Interfaces (Contracts).

2.  **Application** (`src/Modules/*/Application`):
    - Сценарии использования (Use Cases).
    - Зависит от `Domain` и `Shared`.
    - Запрещено использование классов из `Infrastructure`, `App`.
    - Содержит: Actions, Commands, DTOs, Event Listeners.

3.  **Infrastructure** (`src/Modules/*/Infrastructure`):
    - Реализация интерфейсов и работа с внешним миром.
    - Зависит от `Domain`, `Application`, `Shared`.
    - Запрещено использование классов из `App`.
    - Содержит: Repository Implementations (Eloquent), API Clients, DB Migrations.

4.  **Framework** (`app/*`):
    - Точка входа и склейка приложения.
    - Зависит от всех слоев.
    - Содержит: Controllers, Console Commands, Service Providers.

**Shared Kernel (`src/Modules/Shared`):**

- Общий код, доступный всем слоям (DTO, Enums, Traits), не имеет зависимостей от других модулей.
- Общие domain/application элементы по умолчанию сначала рассматриваются для размещения здесь; `src/Support` и `src/Infrastructure` предназначены только для технического и общего инфраструктурного кода, который не должен жить в shared kernel или в конкретном модуле.

### 2.2.2 Правила размещения кода в `src`

- `src/Modules/`:
    - основной каталог модульной бизнес-архитектуры;
    - сюда попадает код, который относится к конкретному модулю (`Crawler`, `Catalog`, `Delivery`, `Intelligence`) или к shared kernel (`Shared`);
    - если код принадлежит одному модулю, по умолчанию он должен оставаться внутри этого модуля.

- `src/Support/`:
    - каталог для общих технических абстракций, помогающих нескольким модулям;
    - использовать только если код не является бизнес-логикой, не относится к shared kernel и не является Laravel glue-кодом из `app/`;
    - допустимые примеры: базовые классы/интерфейсы для pipeline/handler/resolver-паттернов, internal convention helpers;
    - недопустимо превращать этот каталог в свалку случайных helper-классов.

- `src/Infrastructure/`:
    - каталог для общей инфраструктуры, используемой несколькими модулями одновременно;
    - использовать только если размещение в одном конкретном модуле создаст искусственную зависимость остальных модулей от него;
    - допустимые примеры: общие transport/wrapper-компоненты для HTTP, AMQP helper-обвязки, shared integration adapters;
    - модуль-специфичная инфраструктура должна оставаться в `src/Modules/<Module>/Infrastructure`.

- `app/`:
    - framework/glue-слой Laravel;
    - контроллеры, middleware, console commands, service providers уровня приложения и прочий Laravel-специфичный wiring не должны попадать в `src`.

### 2.2.3 Практическое правило выбора каталога

- Если код относится к одному модулю, он остаётся в `src/Modules/<Module>`.
- Если код общий и это domain/application уровень, сначала рассматривать `src/Modules/Shared`.
- Если код общий и технический, но не domain/application shared kernel, рассматривать `src/Support`.
- Если код общий и инфраструктурный, рассматривать `src/Infrastructure`.
- Если код является частью Laravel framework layer, он должен жить в `app/`.

---

## 2.3 Детали модулей

- **Crawler**
    - Есть `FeedFetcherAction`, `RawNewsFactory`, `RssClient`, `TelegramClient`, `RawPublisher`.
    - Источники `rss` и `telegram` реально обрабатываются.
    - Поддержан массовый Telegram fetch через AJAX-pagination (`before`) с встроенным rate-limiting и остановкой по диапазону дат.
    - Парсер Telegram (`DefaultTelegramParser`) корректно извлекает все медиафайлы из альбомов и прикрепленные ссылки.
    - Введена централизованная URL-политика `SourceUrlPolicy`:
        - типо-специфичный allowlist (`rss`/`telegram`) + `global` allowlist;
        - поддержка wildcard-host (`*.example.com`);
        - проверка разрешенных схем источников (`crawler.security.allowed_source_schemes`);
        - SSRF hardening: блокировка локальных/private hosts (`localhost`, `.local`, private/reserved IP) при `crawler.security.deny_private_hosts=true`.
    - Введен sanitizer входящего контента `IncomingContentSanitizer`, который:
        - удаляет опасный HTML (`script/style/iframe/...`) и нормализует текст;
        - фильтрует URL по разрешенным схемам (`crawler.security.allowed_url_schemes`);
        - очищает `content/title/author/categories/links/media` до безопасного формата перед сохранением.
    - Парсинг асинхронный: команда `news:crawl` распределяет задания (`FetchSourceJob`) в очередь `crawler_tasks`.
    - Внедрен мониторинг здоровья источников (**Source Health Tracking**): `FeedFetcherAction` публикует события в `Shared\Domain\Events`, которые слушатель в `Catalog` использует для обновления `last_success_at`, `last_error_at` и `error_streak`.
    - Runtime-контроль ошибок источников расширен:
        - добавлена policy `SourceRuntimeHealthPolicy` (экспоненциальный backoff по `error_streak`);
        - `EloquentSourceRepository` теперь пишет `retry_backoff_state.next_retry_at` при ошибке и сбрасывает backoff при успехе;
        - `news:crawl` и Livewire-runner пропускают источники, находящиеся в backoff-окне;
        - в `news:crawl` добавлена опция `--ignore-backoff` для принудительного запуска.
    - Команда диспетчеризации: `php artisan news:crawl`.

- **Intelligence**
    - Pipeline реализован: dedup -> lang detect -> translate -> classify -> sentiment -> anti-clickbait -> importance -> moderation -> finalize.
    - Сейчас AI-обогащение эвристическое (без реальных LLM провайдеров).
    - Для снижения сцепления с `Catalog` pipeline использует общий контракт `Shared\Domain\Contracts\NewsStore`.
    - После enrichment публикуется событие `Shared\Domain\Events\NewsEnriched`; постановка `PreloadNewsMediaJob` теперь происходит в `Catalog` через listener.
    - Обработка запускается через Laravel Queue (RabbitMQ driver): `php artisan queue:work --queue=crawler_tasks,intelligence_tasks,media_tasks`.

- **Catalog**
    - Репозиторий и модели для хранения сырого и обогащенного контента.
    - Дедуп на уровне БД через `raw_fingerprint` (unique).
    - Поддержка media (`image_url`, `media`) и `source_metadata`.
    - Добавлены индексы производительности под delivery-фильтры:
        - `news_items_source_status_feed_idx` (`source_id`, `status`, `published_at`, `id`) для курсорной ленты по источнику;
        - `news_items_tags_gin_idx` (GIN `jsonb_path_ops`) для `whereJsonContains(tags, ...)`;
        - trigram GIN индексы `news_items_title_original_trgm_idx` и `news_items_title_generated_trgm_idx` для поиска `ILIKE`.
    - Добавлено отдельное хранилище `news_media_assets`:
        - хранит `source_url` (оригинал), `local_path` (локальная копия), MIME/size/checksum и статус загрузки;
        - API отдает `image_url`/`media` как эффективные ссылки (локальные при наличии) + `*_original`/`*_local` для fallback.
    - Добавлена backfill-команда `news:media:backfill` для массового дозаполнения `news_media_assets` по уже существующим `news_items` (режимы `queue`/`sync`, `dry-run`, диапазон `id`, `chunk/limit`).

- **Delivery**
    - API:
        - `GET /api/news`
        - `GET /api/news/{id}`
        - `GET /api/sources`
        - `GET /api/admin/sources` (RBAC)
    - Для media enrichment в delivery-ридере используется собственный `NewsMediaResolver` (без прямой зависимости на `Catalog` contracts).
    - Web-лента на `/` с фильтрами и догрузкой (cursor-based).

## 2.3 Очереди/события

- Laravel Queue (driver `rabbitmq`) используется для внутренних job-очередей:
    - `crawler_tasks`,
    - `intelligence_tasks`,
    - `media_tasks`.
- Межмодульные события (`SourceFetchSucceeded`, `SourceFetchFailed`, `NewsEnriched`) перенесены в `Shared\Domain\Events`.
- Exchange: `news_flow` (topic) используется для доменных AMQP-событий после enrichment.
- Routing keys: `enriched.ready`, `enriched.ready.important`, `enriched.rejected`.
- Очереди exchange-контура: `queue.delivery_feed`, `queue.delivery_push`.
- `news:messaging:setup` настраивает exchange + delivery queues/bindings.

## 2.4 Дедупликация (актуальное решение)

- Fingerprint стратегия:
    1. `source + externalId` (приоритет),
    2. fallback `source + normalized_title + minute_bucket`,
    3. fallback `source + normalized_link + minute_bucket`.
- В БД: unique индекс на `raw_fingerprint`.
- Проверено на Telegram импорте: дубли из одного источника повторно не создаются.

## 2.5 Инфраструктура и локальное хранение данных

- Docker Compose поднимает `app` (RoadRunner), `postgres`, `redis`, `rabbitmq`, `worker` (Laravel Queue worker для `crawler_tasks,intelligence_tasks,media_tasks`). Nginx удален за ненадобностью.
- Build target для Docker-образа вынесен в `DOCKER_BUILD_TARGET` (`local`/`production`) вместо жёсткой привязки к `APP_ENV`.
- Для локальной отладки через Laravel Debugbar в Octane подключен `ResetDebugbarJsRenderer` на `RequestReceived`, чтобы Debugbar не переиспользовал устаревший base URL между запросами и не генерировал asset-ссылки на внутренний порт RoadRunner `:8000`.
- Данные Postgres теперь персистятся на диск проекта:
    - `./.docker-data/postgres:/var/lib/postgresql/data`.
- Локальный Docker-стек обновлен до актуальных стабильных линий образов:
    - `postgres:18-alpine` c явным `PGDATA=/var/lib/postgresql/data/pgdata` для совместимого запуска на PostgreSQL 18;
    - `redis:8-alpine`;
    - `rabbitmq:4.2-management-alpine`;
    - базовый образ приложения `alpine:3.23.3` с установленным системным `curl` (необходим для smoke-тестов и отладки);
    - build-time Composer image `composer:2.9.5`.
- Данные Redis и RabbitMQ также персистятся на диск проекта:
    - `./.docker-data/redis:/data`,
    - `./.docker-data/rabbitmq:/var/lib/rabbitmq`.
- Папка `.docker-data` добавлена в `.gitignore`.
- Замечание из ревью по ext-zip/ext-xml закрыто:
    - runtime-слой Dockerfile собирает `zip` и `xml`.
- **Docker Hardening & Performance**:
    - Образ `runtime` переведен на использование не-root пользователя `www-data` для повышения безопасности.
    - Включено расширение `opcache` с оптимальными настройками для production-контура.
    - Оптимизировано копирование файлов для лучшего использования кэша Docker-слоев.
    - В `Makefile` добавлен отдельный target `make rebuild` для полной пересборки Docker-образов без layer cache.
    - `make up` и `setup-local` теперь повторяют `docker compose up` после `docker compose rm -f -s` по сервисам проекта, если первый запуск упал на конфликте имен/зависших контейнерах.
    - Сервисы данных (`postgres`, `redis`, `rabbitmq`) по умолчанию биндятся только на `127.0.0.1` (через переменные `*_BIND`).
    - RabbitMQ учетные данные в compose берутся из `.env`; дефолт `guest/guest` убран.
    - `RABBITMQ_RETRY_AFTER` увеличен до `180`, чтобы быть больше worker `--timeout=120` и исключить преждевременный requeue.
    - `smoke-api` в `Makefile` использует `http://127.0.0.1:8000` (совместимо с текущим compose без nginx).
    - Для dev включен `RoadRunner reload` через `.rr.yaml`; `make dev`, `make serve` и контейнерный `CMD` запускают Octane с `--rr-config=.rr.yaml`.
    - Старт Octane вынесен в `docker/bin/start-octane.sh`: бинарь `rr` переносится из `/app/rr` в `/tmp/roadrunner-bin/rr` и удаляется из корня проекта; образ приложения добавляет `/tmp/roadrunner-bin` в `PATH`, чтобы `php artisan octane:reload` работал и вне стартового shell без записи в системные каталоги.
    - Старт worker вынесен в `docker/bin/start-worker.sh`: перед `queue:work` автоматически декларируются очереди `crawler_tasks`, `intelligence_tasks`, `media_tasks` (устраняет `basic.get not_found` в RabbitMQ логах).
- Для локальных медиа добавлен обязательный `storage:link` в setup-процессы.

---

## 3) Что осталось сделать по ТЗ (gap list)

## 3.1 Crawler / источники

- [x] Улучшение парсинга Telegram (AJAX-пагинация, остановка по датам, извлечение альбомов и ссылок без бана IP).
- [x] Отслеживание состояния здоровья источников (`last_success_at`, `last_error_at`, `error_streak`) через доменные события.
- [x] Полноценная политика allowlist и security hardening входящего HTML (sanitization).
- [x] Расширить контроль ошибок источников (`error_streak`, `last_error_at`, `last_success_at`) в runtime-логике.

## 3.2 Intelligence / AI

- [ ] Подключить реальные LLM-провайдеры (OpenAI/Anthropic/DeepSeek).
- [ ] Реализовать fallback-chain провайдеров.
- [ ] Добавить лимиты токенов/мин по провайдерам.
- [ ] Кэш ответов в Redis по fingerprint.
- [ ] Уточнить/формализовать prompt-контракты и валидацию ответа модели.

## 3.3 Catalog / поиск

- [x] Добавить/проверить индексы под фактический профиль запросов (GIN/trigram/и т.д.).
- [ ] Реализовать `pgvector`-ветку дедупа/поиска (или задокументированный fallback-only режим).
- [ ] Архивирование старых данных в отдельное хранилище по retention-политике.

## 3.4 Delivery (Web/Admin/Bot)

- [ ] Web сейчас vanilla page; по ТЗ ожидается Blade+Livewire для UI-сценариев.
- [ ] Filament admin: CRUD источников, ошибки, ручной запуск fetch.
- [ ] Telegram Bot (Nutgram): `/settings`, `user_preferences`, push с фильтрацией по prefs.

## 3.5 Observability / Reliability

- [x] Метрики Prometheus (ingest rate, queue depth, latency p95, step success/error).
- [x] Laravel Telescope (локальная отладка).
- [ ] OTel трассировка по стадиям pipeline.
- [ ] Структурный JSON-лог с консистентным контекстом (`module`, `sourceId`, `fingerprint`).
- [ ] Health readiness c учетом threshold глубины очередей.

## 3.6 DevOps / K8s / CI

- [ ] Доработать K8s-манифесты до production-ready (PDB, KEDA/HPA по очереди, probes, secrets/config separation).
- [ ] Проверить rollout-стратегии и отказоустойчивость worker/scheduler.
- [ ] Усилить CI gates на контрактные тесты сообщений/API.

---

## 4) Статус этапов roadmap

- **Init**: выполнен.
- **Core (Crawler+Catalog без AI)**: выполнен.
- **AI Integration**: частично (pipeline есть, реальные LLM пока нет).
- **UI/UX**: частично (web feed готова и имеет infinite scrolling, Livewire/Filament не завершены).
- **Bot**: не выполнен.
- **DevOps**: частично (docker и базовые k8s есть, production hardening не завершен).

---

## 5) Текущие операционные команды

- Старт окружения: `docker compose up -d --build` (сервер автоматически запустится через `php artisan octane:start`)
- Миграции: `docker compose exec -T app php artisan migrate --force`
- Сиды: `docker compose exec -T app php artisan db:seed --force`
- Публичные storage-ссылки: `docker compose exec -T app php artisan storage:link`
- Инициализация топологии RabbitMQ: `docker compose exec -T app php artisan news:messaging:setup`
- Сбор новостей: `docker compose exec -T app php artisan news:crawl`
- Принудительный сбор с игнорированием runtime-backoff: `docker compose exec -T app php artisan news:crawl --ignore-backoff`
- Обработка очередей: `docker compose exec -T app php artisan queue:work --queue=crawler_tasks,intelligence_tasks,media_tasks --tries=3`
- Backfill медиа-ассетов: `docker compose exec -T app php artisan news:media:backfill --dry-run`
- Тесты: `docker compose exec -T app composer test` (или `make test`)
- Статика: `docker compose exec -T app composer analyze` (или `make analyze`)
- **Telescope**: доступен по адресу `http://localhost:8080/telescope` (только в `local` окружении)

- Политика выполнения команд: команды разработки напрямую выполняются внутри контейнера `app`; исключение — `make` из корня проекта на хосте, который оркестрирует контейнерные команды.

---

## 6) Ключевые риски

- Эвристический AI-блок не дает стабильного качества для production.
- Нет контрактной валидации сообщений на уровне schema registry.
- K8s-конфигурация пока без полного production hardening.

---

## 7) Правило актуализации

- При изменении архитектуры, контрактов, очередей, infra-контура, критичных команд:
    1. сначала обновить этот файл,
    2. затем делать кодовые изменения.
- После каждого крупного merge/commit обязательно обновлять секции:
    - «Что реализовано»,
    - «Что осталось»,
    - «Статус roadmap».
