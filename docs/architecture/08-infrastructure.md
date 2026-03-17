# 08. Инфраструктура: Docker, Сервисы, Makefile и Контейнерный Runtime

Этот файл объясняет не бизнес-логику, а среду, в которой она живёт. Для собеседования это важно по двум причинам: во-первых, ты должен уметь объяснить operational picture системы; во-вторых, многие архитектурные решения в коде напрямую связаны с тем, как устроен контейнерный runtime.

## Зачем существует эта часть системы

Проект не существует "в вакууме Laravel". Ему нужны:

- PostgreSQL;
- Redis;
- RabbitMQ;
- отдельный worker runtime;
- Octane/RoadRunner HTTP runtime;
- файловое storage для локальных медиа;
- reproducible local setup через Docker и Make.

## Ключевые файлы

| Файл | Роль |
| --- | --- |
| `docker-compose.yml` | Описание контейнеров локального стека |
| `docker/bin/start-octane.sh` | Старт HTTP runtime |
| `docker/bin/start-worker.sh` | Старт queue worker runtime |
| `.rr.yaml` | Hot reload для RoadRunner |
| `Makefile` | Оркестрация типовых команд разработки |
| `config/queue.php` | Queue backend configuration |
| `config/messaging.php` | RabbitMQ exchange/queue topology для AMQP-событий |
| `config/crawler.php` | Security/runtime policy crawler-а |

## Docker Compose: физическая карта сервисов

### `app`

Основной сервис приложения.

Что делает:

- поднимает HTTP runtime;
- монтирует код проекта в `/app`;
- пробрасывает `8000` наружу как `${APP_PORT:-8080}`;
- пробрасывает Vite port `${VITE_PORT:-5173}`;
- зависит от `postgres`, `redis`, `rabbitmq`.

Healthcheck у него завязан на `http://127.0.0.1:8000/up`.

### `worker`

Основан на том же образе, что и `app`, но запускает:

- `sh docker/bin/start-worker.sh`

То есть это отдельный контейнер только для работы с очередями.
В локальном compose он вынесен в профиль `queue`, чтобы не занимать RAM, пока асинхронный контур не нужен.

### `postgres`

Использует `postgres:18-alpine`.

Данные лежат в:

- `./docker/.data/postgres:/var/lib/postgresql/data`

### `redis`

Использует `redis:8-alpine`.

Данные лежат в:

- `./docker/.data/redis:/data`

### `rabbitmq`

Использует `rabbitmq:4.2-management-alpine`.

Данные лежат в:

- `./docker/.data/rabbitmq:/var/lib/rabbitmq`

Снаружи доступны:

- AMQP port `5672`
- management UI `15672`

### Отдельного `scheduler` сервиса сейчас нет

Это важно проговорить явно: в `docker-compose.yml` сейчас есть `app`, `worker`, `postgres`, `redis`, `rabbitmq`, но нет выделенного сервиса под `php artisan schedule:run` или `php artisan schedule:work`.

При этом schedule definition в коде существует в `routes/console.php`.

Практически это значит:

1. расписание `news:crawl` описано;
2. но в локальном compose нет отдельного процесса, который исполнял бы его автоматически;
3. для ручного и учебного сценария основной командой остаётся `make crawl`.

## Что важно понимать про `app` и `worker`

Хотя оба контейнера построены из одного образа, у них разные runtime-роли:

| Контейнер | Роль |
| --- | --- |
| `app` | HTTP server via Octane/RoadRunner |
| `worker` | Laravel queue worker |

Это даёт:

- независимый lifecycle;
- независимую диагностику;
- возможность масштабировать web и queue отдельно.

## Особенность `make dev`

Есть тонкость локальной разработки: target `make dev` запускает внутри контейнера `app` сразу несколько процессов через `npx concurrently`:

- `start-octane.sh`
- `php artisan queue:listen --tries=1 --timeout=0`
- `php artisan pail --timeout=0`
- `npm run dev`

То есть для interactive dev-потока queue listener может крутиться внутри `app`, даже несмотря на наличие отдельного `worker` сервиса в compose.

Это не противоречие, а два режима:

1. `worker` контейнер как фоновый штатный сервис;
2. `make dev` как удобный interactive developer setup.

## `start-octane.sh`

Скрипт:

1. переносит бинарник `rr` в `/tmp/roadrunner-bin`;
2. при необходимости скачивает свежий RoadRunner binary;
3. добавляет runtime dir в `PATH`;
4. запускает `php artisan octane:start ...`.

Почему это сделано так:

- бинарник не должен жить в смонтированном project dir как mutable runtime-артефакт;
- `octane:reload` должен работать независимо от стартового shell;
- runtime binary удобнее держать вне кода приложения.

## `start-worker.sh`

Перед запуском `queue:work` скрипт явно декларирует очереди:

- `crawler_tasks`
- `intelligence_tasks`
- `media_tasks`

После этого стартует worker:

```bash
php artisan queue:work \
  --queue=crawler_tasks,intelligence_tasks,media_tasks \
  --tries=3 \
  --sleep=1 \
  --timeout=120
```

Это снижает вероятность неинициализированных очередей и логов вида `basic.get not_found`.

## RabbitMQ: две разные роли в проекте

Очень важный архитектурный момент.

### Роль 1. Laravel Queue backend

RabbitMQ используется как transport для:

- `FetchSourceJob`
- `ProcessNewsJob`
- `PreloadNewsMediaJob`
- queued listeners

Очереди:

- `crawler_tasks`
- `intelligence_tasks`
- `media_tasks`

### Роль 2. AMQP exchange для внешних событий

Отдельно есть exchange `news_flow`, который используется для публикации доменных результатов наружу:

- `enriched.ready`
- `enriched.ready.important`
- `enriched.rejected`

Это другой integration contour, не равный Laravel Queue.

## Storage и локальные медиа

Для локально скачанных файлов используется storage disk, обычно `public`.

Поэтому при setup важен:

```bash
php artisan storage:link
```

Без этого `DbNewsMediaResolver` не сможет корректно отдавать URL локальных файлов.

## Makefile как orchestration layer

По проектным правилам:

- `make` запускается на хосте из корня проекта;
- все команды разработки и проверок реально выполняются внутри контейнера `app`.

### Самые важные targets

| Target | Что делает |
| --- | --- |
| `make setup-local` | initial setup: compose up, install, `.env`, key, migrate, storage link |
| `make dev` | interactive development runtime |
| `make test` | reload Octane + unit/feature suite |
| `make test-coverage` | reload Octane + unit/feature suite + pcov coverage gate |
| `make test-arch` | architecture tests |
| `make acceptance` | acceptance tests |
| `make ci-check` | validate + audit + lint-check + analyze + psalm-taint + coverage gate + arch |
| `make agent-check` | reload + lint + docs-deps + analyze + full test-all |
| `make crawl` | ручной запуск `news:crawl` |
| `make media-backfill` | ручной запуск backfill медиа |
| `make logs` | tail контейнерных логов |

### Почему `make` здесь важен

Он:

- стандартизирует локальную среду;
- убирает длинные docker compose команды;
- вшивает project-specific правила вроде reload перед тестами;
- снижает шанс, что разработчик случайно запустит команду вне контейнера.

## База данных и миграции как часть инфраструктурной картины

Проект использует PostgreSQL 18 и JSONB-поля для части динамических данных. Миграции описаны отдельно в документе [11. Модель Данных, Таблицы и Индексы](11-data-model-and-indexes.md), но operationally важно помнить:

1. схема разворачивается через `php artisan migrate`;
2. локальные данные персистятся на диск проекта;
3. таблица `news_vectors` уже существует в схеме, хотя активной vector-логики в runtime пока нет.

## Что выполняется строго внутри Docker

По project rules:

- artisan;
- composer;
- тесты;
- анализаторы;
- npm;
- любые dev-команды.

Исключение — `make`, потому что он как раз orchestration shell на хосте.

Это правило важно не только организационно, но и технически: окружение, расширения PHP и подключение к инфраструктуре ожидаются именно внутри контейнера.

## Какие есть ограничения, риски и edge cases

1. Локальная среда завязана на Docker; запуск "как обычного локального PHP-проекта" не является основным сценарием.
2. Поведение `make dev` и отдельного `worker` контейнера нужно различать.
3. RabbitMQ используется сразу в двух ролях, и это легко спутать при отладке.
4. Healthcheck контейнера `app` завязан на `/up`, тогда как пользователи и операторы могут смотреть на `/health/live` и `/health/ready`.

## Что важно для Octane/RoadRunner и очередей

1. HTTP runtime и queue runtime физически разделены.
2. `octane:reload` относится к HTTP-процессу, а не к worker-контейнеру.
3. Состояние очередей и AMQP exchange надо диагностировать отдельно.

## Что могут спросить на собеседовании

### Вопрос
Почему RabbitMQ в проекте используется сразу в двух режимах?

**Короткий ответ:** как backend для Laravel Queue и как отдельный AMQP exchange для интеграционных событий.

**Развёрнутый ответ:** внутренние jobs и queued listeners используют очереди `crawler_tasks`, `intelligence_tasks`, `media_tasks`. Отдельно модуль Intelligence публикует бизнес-события в topic exchange `news_flow`, чтобы их могли потреблять внешние подписчики.

**На что обратить внимание:** это один из ключевых архитектурных нюансов проекта.

### Вопрос
Зачем отдельный `worker` контейнер, если есть `make dev` с queue listener?

**Короткий ответ:** `make dev` — удобный локальный режим разработки, а `worker` — штатный фоновый сервис.

**Развёрнутый ответ:** в интерактивной разработке полезно видеть queue processing прямо рядом с логами и vite. Но на уровне compose у проекта всё равно есть отдельный worker runtime как самостоятельная роль системы.

**На что обратить внимание:** это хороший ответ про "developer convenience vs operational structure".

### Вопрос
Почему проект так жёстко требует запускать команды внутри Docker?

**Короткий ответ:** чтобы окружение было воспроизводимым и совпадало по расширениям, сервисам и версиям.

**Развёрнутый ответ:** локальный хост не является source of truth для runtime. PHP extensions, бинарники, сервисные адреса и network topology ожидаются именно внутри контейнера. Поэтому `make` выступает как гарант стандартного окружения.

**На что обратить внимание:** связать ответ с reproducibility и onboarding friction reduction.

## Куда смотреть в коде

- `docker-compose.yml`
- `docker/bin/start-octane.sh`
- `docker/bin/start-worker.sh`
- `.rr.yaml`
- `Makefile`
- `config/messaging.php`
- `config/crawler.php`

## Связанные документы

- [../guides/runtime-operations.md](../guides/runtime-operations.md) — практическая карта процессов и scheduler-а
- [../reference/config/env.md](../reference/config/env.md) — какие `.env`-переменные реально влияют на runtime
- [01. Точки Входа, Boot Lifecycle и Octane Runtime](01-entrypoint.md) — lifecycle процессов
- [11. Модель Данных, Таблицы и Индексы](11-data-model-and-indexes.md) — схема БД
- [12. События, Очереди и Messaging](12-events-queues-and-messaging.md) — сообщения и очереди
