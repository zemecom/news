# PROJECT_MEMORY

## 1) Контекст проекта

- Проект: **SmartNews Aggregator** (Laravel 12, PHP 8.5, Modular Monolith).
- Цель: сбор, обработка и доставка новостей с AI-обогащением, с готовностью к запуску в Docker/Kubernetes.
- Текущее состояние: рабочий backend-контур + web-лента + Telegram crawler + очереди RabbitMQ.
- Основной URL в локальной среде: `http://localhost:8080`.
- Карта зависимостей классов: `docs/BUSINESS_LOGIC_DEPENDENCIES.md`.

---

## 2) Что реализовано по ТЗ (детально)

## 2.1 Технологический стек

- **Backend/архитектура**
    - Laravel 12 + strict types.
    - Modular Monolith структура: `src/Modules/{Crawler,Intelligence,Catalog,Delivery,Shared}`.
    - Контейнер модулей подключается через `app/Providers/ModulesServiceProvider.php`.
- **Интеграции**
    - Saloon используется для HTTP-клиентов (RSS/Telegram web endpoint).
    - RabbitMQ через `php-amqplib`.
- **Data**
    - PostgreSQL + JSONB-поля в `sources/news_items`.
    - Redis подключен для инфраструктурного контура.
- **Code quality**
    - Pest + Arch tests, PHPStan, Psalm (в т.ч. taint), Pint, Rector.
    - Обязательный запуск `make ci-check` перед коммитом изменений в `app/` или `src/Modules/`.

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

---

## 2.3 Детали модулей

- **Crawler**
    - Есть `FeedFetcherAction`, `RawNewsFactory`, `RssClient`, `TelegramClient`, `RawPublisher`.
    - Источники `rss` и `telegram` реально обрабатываются.
    - Поддержан массовый Telegram fetch через pagination (`before`) с лимитом.
    - Парсинг асинхронный: команда `news:crawl` распределяет задания (`FetchSourceJob`) в очередь `crawler_tasks`, которые параллельно разбирают воркеры.
    - Команда диспетчеризации: `php artisan news:crawl`.

- **Intelligence**
    - Pipeline реализован: dedup -> lang detect -> translate -> classify -> sentiment -> anti-clickbait -> importance -> moderation -> finalize.
    - Сейчас AI-обогащение эвристическое (без реальных LLM провайдеров).
    - Команда обработки очереди: `php artisan news:process`.

- **Catalog**
    - Репозиторий и модели для хранения сырого и обогащенного контента.
    - Дедуп на уровне БД через `raw_fingerprint` (unique).
    - Поддержка media (`image_url`, `media`) и `source_metadata`.

- **Delivery**
    - API:
        - `GET /api/news`
        - `GET /api/news/{id}`
        - `GET /api/admin/sources` (RBAC)
    - Web-лента на `/` с фильтрами и догрузкой (cursor-based).

## 2.3 Очереди/события

- Exchange: `news_flow` (topic).
- Routing keys: `raw.created`, `raw.retry`, `enriched.ready`, `enriched.ready.important`, `enriched.rejected`.
- Очереди: `queue.raw_ingest`, `queue.news_processing`, `queue.delivery_feed`, `queue.delivery_push`, `queue.news_processing.dlq`.
- Реализованы:
    - setup topology (`news:messaging:setup`),
    - retries/backoff в обработчике,
    - отправка в DLQ при исчерпании попыток.

## 2.4 Дедупликация (актуальное решение)

- Fingerprint стратегия:
    1. `source + externalId` (приоритет),
    2. fallback `source + normalized_title + minute_bucket`,
    3. fallback `source + normalized_link + minute_bucket`.
- В БД: unique индекс на `raw_fingerprint`.
- Проверено на Telegram импорте: дубли из одного источника повторно не создаются.

## 2.5 Инфраструктура и локальное хранение данных

- Docker Compose поднимает `app`, `nginx`, `postgres`, `redis`, `rabbitmq`, `worker` (Intelligence pipeline), `crawler-worker` (Async Jobs crawler_tasks).
- Данные Postgres теперь персистятся на диск проекта:
    - `./.docker-data/postgres:/var/lib/postgresql/data`.
- Папка `.docker-data` добавлена в `.gitignore`.
- Замечание из ревью по ext-zip/ext-xml закрыто:
    - runtime-слой Dockerfile собирает `zip` и `xml`.

---

## 3) Что осталось сделать по ТЗ (gap list)

## 3.1 Crawler / источники

- [ ] Полноценная политика allowlist и security hardening входящего HTML (sanitization).
- [ ] Расширить контроль ошибок источников (`error_streak`, `last_error_at`, `last_success_at`) в runtime-логике.

## 3.2 Intelligence / AI

- [ ] Подключить реальные LLM-провайдеры (OpenAI/Anthropic/DeepSeek).
- [ ] Реализовать fallback-chain провайдеров.
- [ ] Добавить лимиты токенов/мин по провайдерам.
- [ ] Кэш ответов в Redis по fingerprint.
- [ ] Уточнить/формализовать prompt-контракты и валидацию ответа модели.

## 3.3 Catalog / поиск

- [ ] Добавить/проверить индексы под фактический профиль запросов (GIN/trigram/и т.д.).
- [ ] Реализовать `pgvector`-ветку дедупа/поиска (или задокументированный fallback-only режим).
- [ ] Архивирование старых данных в отдельное хранилище по retention-политике.

## 3.4 Delivery (Web/Admin/Bot)

- [ ] Web сейчас vanilla page; по ТЗ ожидается Blade+Livewire для UI-сценариев.
- [ ] Filament admin: CRUD источников, ошибки, ручной запуск fetch.
- [ ] Telegram Bot (Nutgram): `/settings`, `user_preferences`, push с фильтрацией по prefs.

## 3.5 Observability / Reliability

- [ ] Метрики Prometheus (ingest rate, queue depth, latency p95, step success/error).
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
- **UI/UX**: частично (web feed готова, Livewire/Filament не завершены).
- **Bot**: не выполнен.
- **DevOps**: частично (docker и базовые k8s есть, production hardening не завершен).

---

## 5) Текущие операционные команды

- Старт окружения: `docker compose up -d --build`
- Миграции/сиды: `docker compose exec -T app php artisan migrate --force`
- Инициализация топологии RabbitMQ: `docker compose exec -T app php artisan news:messaging:setup`
- Сбор новостей: `docker compose exec -T app php artisan news:crawl`
- Обработка очереди: `docker compose exec app php artisan news:process`
- Тесты: `./vendor/bin/pest`
- Статика: `./vendor/bin/phpstan analyse --memory-limit=1G --debug`

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
