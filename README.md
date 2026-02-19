# SmartNews Aggregator

AI‑агрегатор новостей в формате modular monolith на Laravel. PHP 8.5+.
Система собирает данные из источников, обогащает (перевод/классификация/тональность), хранит и доставляет пользователю.

## Архитектура

- Модули: `src/Modules/{Crawler,Intelligence,Catalog,Delivery}`
- Слои в каждом модуле: `Domain`, `Application`, `Infrastructure`
- Контракты: зависимости между слоями только через интерфейсы (Domain/Contracts)

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

- `make setup-local`: Сберет образы, поднимет контейнеры и настроит окружение.
- `make dev`: Запустит сервер, очереди и Vite одновременно (внутри Docker).
- `make setup-hooks`: Настроит путь для git hooks (если нужно запустить отдельно от `setup-local`).

Доступ: `http://localhost:${APP_PORT:-8080}`, healthchecks: `/health/live`, `/health/ready`.
По умолчанию: Приложение — `8080`, Vite (HMR) — `5173`. Порты настраиваются в `.env`.

## Команды (Makefile)

Все команды выполняются **внутри Docker-контейнера**.

### Тестирование и Качество

```bash
make test          # Unit/Feature тесты (PHPUnit)
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
make serve         # Запуск сервера
make logs          # Просмотр логов контейнеров
```

## Архитектурные правила

- Контроллеры не используют модели напрямую.
- Запрещён `Http::get` (только интеграции).
- Application не зависит от Infrastructure.
- Domain не зависит от Application/Infrastructure.

Тесты архитектуры лежат в `tests/Architecture`.

## CI

`.github/workflows/ci.yml`: прогоняет `make ci-check`.

## Примечания

- **PHP 8.5**: Код использует современные возможности (readonly classes, #[Override] и т.д.).
- **Docker**: Образ `app` содержит PHP-FPM, Composer и Node.js/NPM.
- **Vite**: Фронтенд собирается и обслуживается также внутри контейнера.
- **Secrets**: `.env` копируется из `.env.example` при `setup-local`. Для LLM‑интеграций пропиши свои ключи.
- **Xdebug**: Включён по умолчанию в dev-сборке.

---

## Альтернативный запуск (без Docker)

_Не рекомендуется_, так как инфраструктура (Postgres, RabbitMQ, Redis) всё равно требует Docker. Если необходимо запустить PHP локально, используй стандартные команды Laravel, но убедись, что версия PHP >= 8.5.

---

Лицензия: MIT.
