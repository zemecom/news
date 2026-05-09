# Runtime Operations

Этот файл нужен, чтобы честно объяснить, какие процессы у проекта реально существуют, кто за что отвечает и где именно новички обычно путаются.

## 1. Четыре runtime-контура проекта

В проекте полезно различать не "один Laravel", а четыре разных режима жизни системы:

1. HTTP runtime.
2. Queue runtime.
3. Scheduler definition.
4. Ручные CLI-команды.

## 2. HTTP runtime

### Что это

HTTP обслуживает контейнер `app`.

Его базовый запуск задаётся:

- в `docker/Dockerfile` через `CMD ["sh", "docker/bin/start-octane.sh"]`;
- в `docker/bin/start-octane.sh` через `php artisan octane:start --server=roadrunner ...`.

### Что важно понять

1. Это не `php-fpm`, а `Octane + RoadRunner`.
2. Процесс долгоживущий, то есть код и контейнер приложения держатся в памяти между запросами.
3. Из-за этого после изменения кода для ручной HTTP-проверки нужен `octane:reload`.

### Когда нужен reload

Если ты изменил что-то, что влияет на HTTP-поведение:

- controller;
- request;
- action;
- reader;
- provider;
- middleware;
- view logic.

Тогда перед проверкой выполни:

```bash
docker compose exec -T app php artisan octane:reload
```

## 3. Queue runtime

### Что это

Очереди обслуживает контейнер `worker`.

Он запускается командой:

```bash
docker compose --profile queue up -d worker
```

Если нужно больше пропускной способности, этот же fleet масштабируется одинаковыми репликами:

```bash
docker compose --profile queue up -d --scale worker=2 worker
```

А внутри контейнера уже стартует:

```bash
php artisan queue:work \
  --queue=crawler_tasks,intelligence_tasks,media_tasks \
  --tries=3 \
  --sleep=1 \
  --timeout=120
```

### Какие очереди здесь живут

| Очередь | Что в ней происходит |
| --- | --- |
| `crawler_tasks` | fetch источников |
| `intelligence_tasks` | обработка raw news |
| `media_tasks` | скачивание и локализация медиа |

### Что важно понять

1. `worker` и `app` — это разные long-lived процессы.
2. Падение или зависание worker-а не обязательно сразу ломает HTTP.
3. Проблемы асинхронного пайплайна нужно диагностировать отдельно от web-layer.
4. В локальном Docker-стеке `worker` вынесен в профиль `queue`, чтобы не держать лишнюю память, пока очереди не нужны.
5. Локально это один shared fleet runtime, а не отдельные контейнеры на каждую очередь; детализация по очередям остаётся на уровне RabbitMQ diagnostics и bounded actions.

## 4. `make dev` и почему он немного особенный

Для удобства локальной разработки `make dev` запускает внутри контейнера `app` сразу несколько процессов:

- Octane server;
- `queue:listen`;
- `pail`;
- `npm run dev`.

Это удобный interactive-режим, но его нельзя путать с основной контейнерной ролью `worker`.

То есть локально у тебя могут одновременно существовать:

1. штатный `worker` контейнер из `docker compose --profile queue up -d worker`;
2. queue listener внутри `make dev`.

Для первого знакомства это нормально. Главное понимать, что это dev-удобство, а не отдельная бизнес-логика проекта.

## 5. Scheduler: что есть и чего нет

### Что есть

В проекте определено расписание:

```php
Schedule::command('news:crawl')
    ->everyMinute()
    ->withoutOverlapping();
```

Это лежит в:

- `routes/console.php`

### Чего сейчас нет

В `docker-compose.yml` нет отдельного scheduler-сервиса, который бы запускал `php artisan schedule:run` или `php artisan schedule:work`.

Практический вывод:

1. расписание в коде описано;
2. но отдельный процесс, который бы его регулярно исполнял в локальном Docker-стеке, сейчас явно не поднят;
3. для локального знакомства и ручных проверок надёжнее считать основным сценарием `make crawl`.

### Что значит поле `cron_expression` в `sources`

Поле `cron_expression` сейчас видно в данных и admin read model, но оно не является фактическим source of truth для уже работающего контейнерного scheduler-а.

Для новичка здесь главное не перепутать:

- `routes/console.php` — реально описанное Laravel schedule;
- `sources.cron_expression` — operational/configuration поле источника;
- dedicated scheduler process — в текущем compose не выделен отдельно.

## 6. Ручные CLI-команды

Это одноразовые процессы, которые стартуют, делают работу и завершаются.

Чаще всего нужны:

```bash
make crawl
make media-backfill
make test
make analyze
```

или напрямую внутри Docker:

```bash
docker compose exec -T app php artisan news:crawl
docker compose exec -T app php artisan news:media:backfill
```

Именно это самый безопасный способ познакомиться с проектом, если ты ещё не уверен в scheduler и очередях.

## 7. Как быстро понять, где искать проблему

### HTTP не меняется после правки

Смотри в сторону:

- `octane:reload`;
- long-lived state;
- контейнера `app`.

### Новость не доходит до ленты

Смотри в сторону:

- `worker`;
- Telescope jobs/exceptions;
- `news_items.status`;
- `ProcessRawNewsListener`;
- `NewsProcessingPipeline`.

### Картинка не стала локальной

Смотри в сторону:

- `NewsEnriched`;
- `PreloadNewsMediaJob`;
- таблицы `news_media_assets`;
- storage disk и `storage:link`.

### `/health/ready` зелёный, но бизнес-поток всё равно сломан

Это нормальный сценарий.

`/health/ready` проверяет инфраструктурную доступность:

- DB;
- Redis;
- RabbitMQ.

Но он не отвечает на вопросы:

- исполняются ли jobs вовремя;
- жив ли pipeline логически;
- пусты ли очереди;
- публикуются ли новости.

## 8. Полезный минимум команд

```bash
make logs
make crawl
docker compose exec -T app php artisan octane:reload
docker compose exec -T app php artisan queue:work --stop-when-empty --max-jobs=1 --queue=crawler_tasks,intelligence_tasks,media_tasks --tries=3
docker compose exec -T app php artisan db:seed --class=NewsItemSeeder --force
```

## 9. Куда идти дальше

Если хочешь практический старт, открой:

- [Быстрый старт для новичка](../start/junior-onboarding.md)

Если хочешь понять глубже, как это связано с архитектурой:

- [Точка входа и жизненный цикл приложения](../architecture/01-entrypoint.md)
- [Инфраструктура и runtime-контур](../architecture/08-infrastructure.md)
- [События, очереди и messaging](../architecture/12-events-queues-and-messaging.md)
