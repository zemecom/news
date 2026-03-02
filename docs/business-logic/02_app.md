# 02. Framework Layer: Что Реально Живёт в `app/`

В модульном монолите самая частая ошибка мышления такая: человек видит Laravel-папку `app/` и автоматически считает, что именно там лежит основная логика. В этом проекте это не так. Главная логика размазана по модулям в `src/Modules`, а `app/` в основном играет роль framework glue-слоя.

## Зачем существует эта часть системы

`app/` отвечает за интеграцию проекта с Laravel runtime:

- контроллеры принимают HTTP;
- middleware режут доступ и добавляют инфраструктурное поведение;
- команды дают CLI точки входа;
- service provider-ы собирают приложение;
- Filament/Livewire подключают админский UI;
- глобальные framework-specific модели вроде `User` остаются там, где их ожидает Laravel ecosystem.

## Ключевые файлы и классы

| Путь | Роль |
| --- | --- |
| `app/Providers/ModulesServiceProvider.php` | Включает модульные service provider-ы |
| `app/Providers/AppServiceProvider.php` | Глобальный AMQP connection и локальный Telescope |
| `app/Http/Controllers/...` | HTTP surface проекта |
| `app/Http/Requests/Api/NewsIndexRequest.php` | Валидация query-параметров ленты |
| `app/Http/Middleware/EnsureUserIsAdmin.php` | RBAC для admin API |
| `app/Console/Commands/*.php` | CLI точки входа |
| `app/Providers/Filament/AdminPanelProvider.php` | Конфигурация админ-панели |
| `app/Models/User.php` | Laravel auth-модель пользователя |
| `app/Services/*.php` | Глобальные инфраструктурные сервисы приложения |

## Как `app/` собирает приложение

### `ModulesServiceProvider`

Этот provider делает одну простую, но системообразующую вещь: регистрирует service provider-ы модулей:

- `CrawlerServiceProvider`
- `IntelligenceServiceProvider`
- `CatalogServiceProvider`
- `DeliveryServiceProvider`

Зачем это нужно:

1. Laravel сам по себе не знает о `src/Modules`.
2. Контракты и реализации внутри модулей должны попасть в service container.
3. Слушатели событий и биндинги модулей должны быть зарегистрированы централизованно.

Это важный architectural seam: framework знает о модулях, а модули не обязаны знать о framework-композиции.

### `AppServiceProvider`

Здесь регистрируется глобальный `AMQPStreamConnection` на основе `queue.connections.rabbitmq`, а также локально подключается `TelescopeServiceProvider`.

Это хороший пример правильного содержимого `app/`:

- код явно framework/integration-specific;
- он не принадлежит одному конкретному бизнес-модулю;
- он нужен на уровне всего приложения.

## Контроллеры: thin controllers, но без фанатизма

### `NewsController`

Это главный публичный API-контроллер.

Он обслуживает:

- `GET /api/news`
- `GET /api/news/{id}`
- `GET /api/sources`

Что он делает правильно:

1. Получает typed input через `NewsIndexRequest`.
2. Собирает DTO `NewsFeedFilters`.
3. Передаёт всё в `ListNewsAction`, `ShowNewsAction`, `ListPublicSourcesAction`.
4. Формирует JSON response shape.

Чего он не делает:

- не пишет SQL;
- не ходит в RabbitMQ;
- не работает напрямую с моделями `NewsItem` или `Source`;
- не реализует доменные правила.

### `SourceController`

Admin API endpoint `GET /api/admin/sources`.

Он делегирует чтение в `ListSourcesAction`. Это read-only endpoint. Никакого админского CRUD через этот controller сейчас нет, только чтение списка.

### `FeedPageController`

Главная web-страница `/` просто возвращает view `feed`. Это важно: web-страница не дублирует backend-логику ленты на сервере. Реальный read flow остаётся в API/Delivery.

### `HealthController`

Есть два health endpoint-а:

- `/health/live` — просто возвращает `status=ok`;
- `/health/ready` — вызывает `HealthCheckService` и проверяет `db`, `redis`, `rabbitmq`.

Плюс в `bootstrap/app.php` включён стандартный Laravel health endpoint `/up`.

## Form Request: `NewsIndexRequest`

Этот класс валидирует query parameters для ленты новостей:

- `cursor`
- `per_page`
- `category`
- `sentiment_min`
- `sentiment_max`
- `important`
- `date_from`
- `date_to`
- `q`

Кроме обычных правил, он делает post-validation checks:

1. `sentiment_min <= sentiment_max`
2. `date_from <= date_to`

То есть контроллер получает уже нормализованный и валидированный input layer.

## Middleware и доступ

### `EnsureUserIsAdmin`

Этот middleware:

1. Возвращает `401`, если пользователь не аутентифицирован.
2. Возвращает `403`, если пользователь аутентифицирован, но не admin.
3. Пропускает дальше только `App\Models\User` с `role=admin`.

Он доступен как alias `role.admin` и используется в `routes/api.php`.

### `AutoLoginAdmin`

Это локальный convenience middleware для Filament admin panel:

- если `app()->isLocal()`;
- если включён `ADMIN_AUTO_LOGIN`;
- если пользователь не залогинен;

тогда в сессию логинится первый пользователь из базы.

Это не domain logic. Это чисто локальное dev-удобство.

## Console Commands как framework entrypoints

### `NewsCrawlCommand`

Самая важная CLI-команда проекта.

Что она реально делает:

1. Читает активные `Source` через Eloquent-модель `Modules\Catalog\Infrastructure\Persistence\Models\Source`.
2. Учитывает фильтр `--source-id`.
3. Парсит `date-from`, `date-to`, `limit`.
4. Переключает режимы `sync` и `async`.
5. Проверяет backoff через `SourceRuntimeHealthPolicy`.
6. Автоматически подбирает `dateFrom` от последней новости источника, если пользователь не задал диапазон явно.
7. Либо диспатчит `FetchSourceJob`, либо вызывает `FeedFetcherAction` синхронно.

Очень важная interview-деталь: эта команда **не идеально чистая архитектурно**, потому что в framework-слое напрямую использует Eloquent-модели `Source` и `NewsItem`. Это не катастрофа, но это именно компромисс, а не образцовая гексагональная изоляция.

### `NewsMediaBackfillCommand`

Операционная команда для дозаполнения `news_media_assets` по уже существующим новостям.

Поддерживает:

- диапазон `from-id / to-id`
- `limit`
- `chunk`
- `queue`
- `--all`
- `--sync`
- `--dry-run`

Это полезно для миграционного и операционного сценария: если структура хранения медиа появилась позже основного контента, можно переиндексировать существующие записи без нового краулинга.

### `MessagingSetupCommand`

Оборачивает `MessagingTopologyService` и декларативно создаёт exchange, queues и bindings в RabbitMQ. Это инфраструктурный bootstrap.

### `HealthCheckCommand`

CLI-проверка здоровья приложения для DB/Redis/RabbitMQ.

## Глобальные сервисы уровня приложения

### `MessagingTopologyService`

Создаёт topology для exchange `news_flow`:

- exchange type: `topic`
- queue `queue.delivery_feed`
- queue `queue.delivery_push`
- bind `enriched.ready`
- bind `enriched.ready.important`

Важно: это не Laravel Queue. Это отдельный AMQP exchange-контур.

### `HealthCheckService`

Проверяет:

- `DB::connection()->getPdo()`
- `Redis::command('ping')`
- открытие/закрытие AMQP channel

Это readiness-check уровня инфраструктуры, а не бизнес-диагностика.

## Filament и framework-specific UI

### `AdminPanelProvider`

Поднимает Filament panel:

- id: `admin`
- path: `/admin`
- login enabled
- auto-discover resources/pages/widgets
- middleware stack для cookies/session/csrf
- добавляет `AutoLoginAdmin`

### `SourceResource`

Resource построен поверх Eloquent-модели `Source`, то есть админка напрямую работает с persistence model Catalog-модуля. Это нормальный pragmatic shortcut для admin-поверхности.

### `SourcesTable`

Показывает:

- `name`
- `url`
- `type`
- `is_active`
- `news_items_count`
- `latest_article_at`
- `earliest_article_at`
- `last_success_at`
- `error_streak`

Есть action `Run`, который открывает модалку с crawler UI.

## `User` как исключение из модульной логики

Модель `App\Models\User` остаётся в `app/Models` по pragmatic причинам:

1. Laravel auth ecosystem ожидает её там.
2. Filament напрямую интегрируется с auth-моделью.
3. Отдельного модуля `Auth` в проекте пока нет.

Она знает о `role`, умеет `isAdmin()` и реализует `FilamentUser`.

## Какие данные здесь проходят и как они меняются

| Уровень | Данные |
| --- | --- |
| HTTP controller | `Request`, `JsonResponse`, DTO-фильтры |
| Middleware | текущий пользователь и access decision |
| Commands | CLI options, коллекции Eloquent-моделей, dispatch jobs |
| Services | технические результаты health/messaging bootstrap |
| Filament | persistence records для админского UI |

## Какие есть ограничения, риски и edge cases

1. `NewsCrawlCommand` работает не через domain contract, а через Eloquent-модели.
2. `app/` всё равно местами знает слишком много о runtime-деталях модулей.
3. Admin-панель завязана на persistence-модели, а не на отдельный application facade.
4. `AutoLoginAdmin` удобен локально, но его нельзя воспринимать как production-аутентификацию.

## Что важно для Octane/RoadRunner и очередей

1. Controllers и middleware не должны хранить mutable состояние между запросами.
2. Глобальные singletons из providers должны быть stateless или очень аккуратными.
3. Console commands и HTTP-runtime имеют разный lifecycle, это нужно проговаривать отдельно.

## Что могут спросить на собеседовании

### Вопрос
Почему `app/` в этом проекте не содержит бизнес-логику?

**Короткий ответ:** потому что бизнес-логика вынесена в модульные слои `src/Modules`, а `app/` оставлен как Laravel glue layer.

**Развёрнутый ответ:** `app/` нужен, чтобы интегрировать проект с HTTP, CLI, auth, middleware, providers, Filament и другими framework-specific точками. Это снижает связность между бизнес-кодом и Laravel runtime.

**На что обратить внимание:** упомяни, что это цель архитектуры, но не абсолютная чистота.

### Вопрос
Насколько тонкие контроллеры в этом проекте?

**Короткий ответ:** достаточно тонкие: они валидируют input, собирают DTO и делегируют работу action-классам.

**Развёрнутый ответ:** `NewsController` почти полностью соответствует идее thin controller. Но в целом правильнее говорить не "они нулевые", а "они держат transport concerns и orchestration request/response".

**На что обратить внимание:** не обещай, что любой controller здесь идеально чистый, если ты не проверил код.

### Вопрос
Есть ли в `app/` архитектурные компромиссы?

**Короткий ответ:** да, самый заметный — `NewsCrawlCommand` использует persistence-модели напрямую.

**Развёрнутый ответ:** это упрощает операционный сценарий и читаемость команды, но с точки зрения чистой hexagonal границы framework слой становится чуть сильнее связан с persistence-моделью, чем хотелось бы.

**На что обратить внимание:** на интервью это лучше подавать как осознанный trade-off, а не как "ошибку".

## Куда смотреть в коде

- `bootstrap/app.php`
- `app/Providers/ModulesServiceProvider.php`
- `app/Providers/AppServiceProvider.php`
- `app/Http/Controllers/Api/NewsController.php`
- `app/Http/Controllers/Api/Admin/SourceController.php`
- `app/Http/Controllers/Web/FeedPageController.php`
- `app/Http/Controllers/HealthController.php`
- `app/Http/Requests/Api/NewsIndexRequest.php`
- `app/Http/Middleware/EnsureUserIsAdmin.php`
- `app/Console/Commands/NewsCrawlCommand.php`
- `app/Console/Commands/NewsMediaBackfillCommand.php`
- `app/Providers/Filament/AdminPanelProvider.php`
- `app/Models/User.php`

## Связанные документы

- [03. Модуль Delivery: Как Проект Отдаёт Данные Наружу](03_delivery.md) — как controllers делегируют чтение в Delivery
- [07. Админка, Диагностика и Operational Debugging](07_admin_and_debugging.md) — что поверх `app/` построено для администрирования и диагностики
- [09. Как Добавлять Новую Фичу в Этот Проект](09_how_to_add_feature.md) — как понять, должен ли новый код жить в `app/` или в модуле
