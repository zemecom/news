# SmartNews Aggregator

AI‑агрегатор новостей в формате modular monolith на Laravel. Система собирает данные из источников, обогащает (перевод/классификация/тональность), хранит и доставляет пользователю.

## Архитектура

- Модули: `src/Modules/{Crawler,Intelligence,Catalog,Delivery}`
- Слои в каждом модуле: `Domain`, `Application`, `Infrastructure`
- Контракты: зависимости между слоями только через интерфейсы (Domain/Contracts)

## Быстрый старт (Docker)

```bash
make up
make migrate
```

Доступ: `http://localhost:8080`, healthchecks: `/health/live`, `/health/ready`.

## Локальный запуск (без Docker)

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve
```

Для полного дев-стека (сервер + очередь + логи + Vite):

```bash
make dev
```

## Команды

### Тесты
```bash
make smoke-api
make test
make test-arch
make acceptance
make test-all
```

### Очереди и краулер
```bash
make messaging-setup
make crawl
make process-once
```

`news:process` обрабатывает сообщения с ретраями (5/15/60), максимум 5 попыток, затем отправляет в `queue.news_processing.dlq`.

### Качество кода
```bash
make analyze
make lint
make lint-check
make rector
make rector-check
make psalm
make psalm-taint
make validate
make audit
```

### Локальный bootstrap
```bash
make setup-local
```

> Цели Makefile проксируют `composer` внутри контейнера; CI использует те же скрипты.

## Архитектурные правила

- Контроллеры не используют модели напрямую.
- Запрещён `Http::get` (только интеграции).
- Application не зависит от Infrastructure.
- Domain не зависит от Application/Infrastructure.

Тесты архитектуры лежат в `tests/Architecture`.

## CI

`.github/workflows/ci.yml`: validate → pint (test) → phpstan → psalm taint → pest.

## Примечания

- Для LLM‑интеграций нужны ключи в `.env`, базовый запуск без них возможен.
- Docker: `app` — php-fpm, nginx — отдельный контейнер.
- Makefile заточен под docker compose, но есть цели для локального dev (`serve`, `queue`, `dev`).
- Конфиг Rector: `rector.php`, лучше сначала `rector:check`.
- Конфиг Psalm: `psalm.xml`, для безопасности — `psalm:taint`.
- Xdebug ставится только в dev (build arg `WITH_XDEBUG`, по умолчанию 1 в compose); в прод соберите образ с `WITH_XDEBUG=0`.
- Продовый compose-оверрайд: `docker-compose -f docker-compose.yml -f docker-compose.prod.yml up -d` (Xdebug отключён).

## Лицензия

MIT.
