# 07. Админка, Диагностика и Operational Debugging

Асинхронный backend без нормальной диагностики быстро превращается в "чёрный ящик". В этом проекте есть несколько разных слоёв наблюдаемости и администрирования: публичные health endpoints, admin API, Filament panel, Telescope, логика ручного запуска crawler-а и CLI-команды.

## Зачем существует эта часть системы

Нужно уметь ответить на вопросы:

- живо ли приложение вообще;
- доступна ли инфраструктура;
- какие источники падают;
- когда последний раз источник успешно обрабатывался;
- как вручную инициировать crawler flow;
- где смотреть падения jobs и exception stack traces;
- как админ видит состояние системы.

## Ключевые файлы и классы

| Путь | Роль |
| --- | --- |
| `routes/api.php` | Admin API route и middleware stack |
| `app/Http/Middleware/EnsureUserIsAdmin.php` | RBAC для admin API |
| `app/Http/Controllers/Api/Admin/SourceController.php` | JSON admin endpoint источников |
| `app/Providers/Filament/AdminPanelProvider.php` | Конфигурация `/admin` панели |
| `app/Filament/Resources/Sources/*` | CRUD/read UI для источников |
| `app/Livewire/CrawlerLog.php` | Ручной запуск crawler-а и просмотр лога |
| `app/Http/Controllers/HealthController.php` | `/health/live` и `/health/ready` |
| `app/Services/HealthCheckService.php` | Проверка DB/Redis/RabbitMQ |
| `app/Providers/TelescopeServiceProvider.php` | Подключение Telescope в local |

## Admin API

### Route

`GET /api/admin/sources`

На route навешаны middleware:

- `auth`
- `role.admin`

### `EnsureUserIsAdmin`

Middleware возвращает:

- `401 Unauthenticated`, если пользователя нет;
- `403 Forbidden`, если пользователь не admin.

Проверка идёт через `App\Models\User::isAdmin()`, то есть основана на поле `role`.

### Что отдаёт admin endpoint

`SourceController@index` делегирует чтение в `ListSourcesAction`, а тот использует `SourceAdminReader`.

Admin response включает operational поля:

- `last_success_at`
- `last_error_at`
- `error_streak`
- `cron_expression`
- `language_default`
- `is_active`

Это уже ближе к operational read model, чем к пользовательскому API.

## Filament admin panel

### `AdminPanelProvider`

Панель живёт по пути `/admin` и включает:

- login screen;
- resource discovery;
- dashboard;
- session/cookie/csrf middleware stack;
- `AutoLoginAdmin` в локальной среде.

### `AutoLoginAdmin`

Локально при `ADMIN_AUTO_LOGIN=true` middleware может автоматически залогинить первого пользователя. Это удобно для разработки, но к production security отношения не имеет.

### `SourceResource`

Filament resource завязан прямо на Eloquent-модель `Source`. Это сознательный pragmatic shortcut: admin UI работает непосредственно с persistence model, без дополнительного application facade.

### `SourcesTable`

Показывает не только базовые поля, но и operational метрики:

- общее число статей у источника;
- дату самой новой статьи;
- дату самой старой статьи;
- дату последнего успешного запуска;
- текущий `error_streak`.

Есть также action `Run`, который открывает кастомную модалку ручного запуска crawler-а.

## `CrawlerLog` Livewire-компонент

Это удобный bridge между админским UI и crawler runtime.

### Что умеет

1. Принимать `sourceId`, диапазон дат и limit.
2. Проверять backoff для конкретного источника через `SourceRuntimeHealthPolicy`.
3. Для конкретного источника диспатчить `FetchSourceJob`.
4. Для общего случая запускать `Artisan::call('news:crawl', ...)`.
5. Писать лог в `storage/logs/crawler-run.log`.
6. Периодически обновлять содержимое лога в UI.

Это хороший operational shortcut: админ может увидеть поведение crawler-а без прямого доступа к консоли.

## Health endpoints

### `/health/live`

Всегда возвращает `{"status":"ok"}`. Это liveness-проверка: жив ли сам процесс приложения.

### `/health/ready`

Вызывает `HealthCheckService` и проверяет:

- `db`
- `redis`
- `rabbitmq`

Если все проверки зелёные — HTTP `200`, иначе `503`.

### `/up`

Кроме кастомных health endpoint-ов, в `bootstrap/app.php` включён стандартный Laravel health route `/up`, который использует контейнерный healthcheck `app` сервиса.

## HealthCheckService

Проверки выполняются так:

- `DB::connection()->getPdo()`
- `Redis::command('ping')`
- открыть/закрыть AMQP channel

Важно: readiness здесь проверяет инфраструктурную доступность, но не уровень "есть ли backlog в очередях" или "насколько старые последние сообщения". Это честно надо проговаривать как ограничение.

## Telescope

`AppServiceProvider` регистрирует `TelescopeServiceProvider` только в local environment.

Что полезно смотреть в Telescope:

- Requests
- Jobs
- Commands
- Logs
- Exceptions

Особенно полезно для проекта с очередями:

1. видеть, что `FetchSourceJob` был поставлен;
2. видеть, что `ProcessRawNewsListener` реально исполнился;
3. видеть stack trace падений;
4. понимать, где именно оборвался асинхронный pipeline.

## Практические ручные сценарии диагностики

### Сценарий 1. Источник перестал обновляться

Проверь:

1. `GET /api/admin/sources` или таблицу Filament `Sources`.
2. `error_streak`, `last_success_at`, `last_error_at`.
3. Не находится ли источник в backoff.
4. Логи crawler-а или Telescope jobs.
5. Ручной запуск через `CrawlerLog` или `php artisan news:crawl --source-id=... --ignore-backoff`.

### Сценарий 2. Новость не появляется в ленте

Проверь:

1. дошёл ли краулер до `ProcessNewsJob`;
2. исполнился ли `ProcessRawNewsListener`;
3. не отброшена ли новость на `DeduplicateStep`;
4. не стала ли она `rejected`;
5. сохранилась ли запись в `news_items` со статусом `published`.

### Сценарий 3. Картинка не локализуется

Проверь:

1. есть ли `NewsEnriched` событие;
2. создан ли `PreloadNewsMediaJob`;
3. какие записи лежат в `news_media_assets`;
4. какой у asset `download_status`;
5. существует ли файл на storage disk.

## Какие есть ограничения, риски и edge cases

1. Readiness не учитывает глубину очередей и старость backlog.
2. `CrawlerLog` использует файловый лог и Livewire-обновление, то есть это локальный/операционный инструмент, а не production-grade observability.
3. Filament работает поверх persistence-моделей, а не через отдельные application services.
4. Auto-login полезен локально, но легко перепутать с настоящей схемой аутентификации, если не проговорить контекст.

## Что важно для Octane/RoadRunner и очередей

1. Проблемы очередей чаще диагностируются не через HTTP request, а через jobs/worker tooling.
2. Из-за long-lived процессов поведение может зависеть от того, был ли reload/restart.
3. Health endpoints отвечают только за доступность базовых инфраструктурных зависимостей, а не за корректность бизнес-потоков.

## Что могут спросить на собеседовании

### Вопрос
Как в проекте проверяется, что приложение "готово"?

**Короткий ответ:** через `/health/ready`, который проверяет DB, Redis и RabbitMQ.

**Развёрнутый ответ:** `HealthController@ready` вызывает `HealthCheckService`, тот делает простые connectivity checks. Это readiness уровня инфраструктуры, а не бизнес-readiness с учётом backlog и задержек pipeline.

**На что обратить внимание:** проговори ограничение этого healthcheck.

### Вопрос
Как администратор видит состояние источников?

**Короткий ответ:** через admin API и Filament resource `Sources`.

**Развёрнутый ответ:** источники доступны по `GET /api/admin/sources` и через `/admin`. Там можно увидеть `is_active`, `last_success_at`, `last_error_at`, `error_streak`, количество статей и вручную инициировать запуск.

**На что обратить внимание:** `error_streak` и backoff надо уметь связать с `SourceRuntimeHealthPolicy`.

### Вопрос
Чем Telescope полезен в асинхронной системе?

**Короткий ответ:** он позволяет видеть jobs, commands, exceptions и логи в одном месте.

**Развёрнутый ответ:** в проекте с несколькими очередями и event-driven flow Telescope помогает понять, какой job был поставлен, какой listener исполнился и на каком этапе оборвался pipeline. Это особенно важно, когда ошибка происходит вне HTTP request.

**На что обратить внимание:** Telescope включён только локально.

## Куда смотреть в коде

- `routes/api.php`
- `app/Http/Middleware/EnsureUserIsAdmin.php`
- `app/Http/Controllers/Api/Admin/SourceController.php`
- `app/Providers/Filament/AdminPanelProvider.php`
- `app/Filament/Resources/Sources/SourceResource.php`
- `app/Filament/Resources/Sources/Tables/SourcesTable.php`
- `app/Livewire/CrawlerLog.php`
- `app/Http/Controllers/HealthController.php`
- `app/Services/HealthCheckService.php`
- `app/Models/User.php`

## Связанные документы

- [08. Инфраструктура: Docker, Сервисы, Makefile и Контейнерный Runtime](08_infrastructure.md) — где физически живут DB/Redis/RabbitMQ
- [12. События, Очереди и Messaging](12_events_queues_and_messaging.md) — какие очереди диагностировать
- [13. Безопасность, Отказы и Failure Modes](13_security_and_failure_modes.md) — как интерпретировать backoff и отказы
