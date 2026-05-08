# SmartNews Aggregator

AI‑агрегатор новостей в формате modular monolith на Laravel. PHP 8.5+.
Система собирает данные из источников, обогащает (перевод/классификация/тональность), хранит и доставляет пользователю.

## Архитектура

- Модули: `src/Modules/{Crawler,Intelligence,Catalog,Delivery,Shared}`
- Верхнеуровневые зоны в `src`: `Modules/`, `Support/`, `Infrastructure/`
- Слои в каждом модуле: `Domain`, `Application`, `Infrastructure`
- Контракты: зависимости между слоями только через интерфейсы (Domain/Contracts)
- Локальный HTTP-сервер: Laravel Octane + RoadRunner

Карта документации и маршруты чтения собраны в `docs/README.md`.

## Требования и установка

Для работы с проектом вам понадобятся **Docker** и **GNU Make**.

### 1. Docker

Установите [Docker Desktop](https://www.docker.com/products/docker-desktop/) (macOS/Windows) или Docker Engine (Linux).

### 2. GNU Make

- **macOS**: Обычно уже установлен. Если нет, выполните `xcode-select --install` или используйте Homebrew: `brew install make`.
- **Linux (Ubuntu/Debian)**: `sudo apt update && sudo apt install build-essential`.
- **Windows**: Рекомендуется использовать **WSL2** (Make там есть по умолчанию) или установить через [Chocolatey](https://chocolatey.org/): `choco install make`.

---

## Быстрый старт (Docker)

Проект полностью докеризирован. Тебе нужен только **Docker** и **Make**.

```bash
make setup-local
make dev
```

- `make setup-local`: Сберет образы, поднимет базовый стек (`app`, `postgres`, `redis`, `rabbitmq`, `qdrant`) и настроит окружение.
- `make dev`: Запустит сервер, очереди и Vite одновременно (внутри Docker).
- `make setup-hooks`: Настроит путь для git hooks (если нужно запустить отдельно от `setup-local`).
- Для управляемых фоновых очередей используются три отдельных runtime-контейнера: `crawler-worker`, `intelligence-worker`, `media-worker`.
- Их можно поднять разом через `make worker-up`.
- Отдельный internal-sidecar `worker-control` стартует в базовом стеке и обслуживает безопасный HTTP control-plane для `/admin/workers`, не давая `app`-контейнеру прямой доступ к Docker socket.

Доступ: `http://localhost:${APP_PORT:-8080}`, healthchecks: `/health/live`, `/health/ready`.
По умолчанию: Приложение — `8080`, Vite (HMR) — `5173`. Порты настраиваются в `.env`.
Локальный `APP_URL` по умолчанию: `http://localhost:8080`.

### Первый осмысленный запуск

После `make setup-local` и `make dev` проект уже поднимется, но demo-лента новостей не появится сама. Для первого знакомства с проектом выполни ещё:

```bash
docker compose exec -T app php artisan db:seed --force
docker compose exec -T app php artisan db:seed --class=NewsItemSeeder --force
```

Затем проверь:

```bash
curl "http://localhost:8080/api/sources"
curl "http://localhost:8080/api/news?per_page=2"
```

Это важный onboarding-шаг: стандартный `DatabaseSeeder` добавляет пользователей и источники, а demo-news лежат в отдельном `NewsItemSeeder`.

### Локальная отладка

- `Telescope`: [http://localhost:8080/telescope](http://localhost:8080/telescope) в `local` окружении.
- `Debugbar`: включается через `DEBUGBAR_ENABLED=true` в `.env`.
- При работе через Octane Debugbar сбрасывает внутренний JS renderer на каждый запрос, чтобы asset URL не залипал на внутреннем порту RoadRunner `:8000`.

## Если ты впервые в проекте

Начни не с больших архитектурных глав, а с практического маршрута:

1. [Быстрый старт для новичка](docs/start/junior-onboarding.md)
2. [News API: запросы и ответы](docs/reference/api/news-api.md)
3. [Runtime и операционные процессы](docs/guides/runtime-operations.md)
4. [Конфигурация окружения](docs/reference/config/env.md)
5. [Учебный маршрут по проекту](docs/start/learning-path.md)

Если нужен уже глубокий архитектурный разбор модулей и runtime-потоков, переходи в `docs/architecture/`.

## Команды (Makefile)

Все команды выполняются **внутри Docker-контейнера**.

### Тестирование и Качество

```bash
make test          # Unit/Feature тесты без acceptance и arch
make test-coverage # Unit/Feature тесты с pcov и coverage gate
make test-arch     # Проверка архитектурных правил (Pest)
make ci-check      # Полный прогон (Lint, PHPStan, Psalm, Tests)
make smoke-api     # Базовый тест API
```

### Статический анализ и Линтинг

```bash
make analyze       # PHPStan
make psalm         # Psalm
make lint          # Pint (исправление стиля)
make rector        # Rector (авто-рефакторинг)
```

### Операции

```bash
make crawl         # Запуск краулера вручную
make queue         # Прослушивание очереди
make worker-up     # Поднять все managed queue workers
make serve         # Запуск сервера
make logs          # Просмотр логов контейнеров
```

## Архитектурные правила

- Контроллеры не используют модели напрямую.
- Запрещён `Http::get` (только интеграции).
- Application не зависит от Infrastructure.
- Domain не зависит от Application/Infrastructure.
- `src/Support` не должен превращаться в свалку helper-классов без явной архитектурной роли.
- `src/Infrastructure` используется только для общей инфраструктуры, а не для модуль-специфичных адаптеров.

Тесты архитектуры лежат в `tests/Architecture`.

## CI

`.github/workflows/ci.yml`: на `push` в удалённую `main` и на `pull_request` в `main` гоняет детерминированный quality gate (`validate`, `audit`, `lint`, `phpstan`, `psalm`, `coverage gate`, `arch`), а acceptance suite запускается отдельно по расписанию и вручную через `workflow_dispatch`.

## Git Workflow

- Единственная рабочая и интеграционная ветка: `main`.
- Локальная разработка и push идут напрямую через `main`.
- CI привязан к `main`.

## Документация

Основная точка входа в документацию: [Навигация по документации](docs/README.md).

Внутри `docs/` материалы разложены по назначению:

1. `start/` — практический onboarding и учебный путь.
2. `architecture/` — подробные архитектурные и runtime-разборы.
3. `guides/` и `reference/` — операционные playbook-документы, API и конфигурация.
4. `interview/` — interview pack и шпаргалки.

## Примечания

- **PHP 8.5**: Код использует современные возможности (readonly classes, #[Override] и т.д.).
- **Docker**: Локальный стек использует `postgres:18-alpine`, `redis:8-alpine`, `rabbitmq:4.2-management-alpine`, `qdrant`; образ `app` запускает Laravel Octane на RoadRunner и содержит Composer и Node.js/NPM. `worker` вынесен в профиль `queue`, чтобы не занимать RAM без необходимости.
- **Workers Runtime Control**: В админке доступна отдельная страница `/admin/workers` с runtime health, bounded queue actions, failed jobs, queue preview и Docker Control. Реальные `start/stop/restart/logs/stats` выполняются через sidecar `worker-control`, а не через прямой доступ web/app-контейнера к Docker socket.
- **PostgreSQL 18**: После обновления с ветки `17` существующий каталог `./docker/.data/postgres` может потребовать миграции данных или пересоздания локальной базы, если данные не нужны.
- **Vite**: Фронтенд собирается и обслуживается также внутри контейнера.
- **Secrets**: `.env` копируется из `.env.example` при `setup-local`. Для LLM‑интеграций пропиши свои ключи.
- **Xdebug**: По умолчанию выключен; включается через `WITH_XDEBUG`, `WITH_XDEBUG_WORKER` и `XDEBUG_MODE` в `.env` с последующей пересборкой соответствующего контейнера.

---

## Альтернативный запуск (без Docker)

_Не рекомендуется_, так как инфраструктура (Postgres, RabbitMQ, Redis) всё равно требует Docker. Если необходимо запустить PHP локально, используй стандартные команды Laravel, но убедись, что версия PHP >= 8.5.

---

Лицензия: MIT.
