# 06. Модули Catalog и Shared: Хранение, Контракты и Межмодульные События

`Catalog` и `Shared` лучше читать вместе. В текущем проекте они образуют связку "storage + shared language". `Catalog` хранит данные и знает про persistence, а `Shared` даёт общие DTO, события, enum-ы и shared services, через которые остальные модули координируются.

## Зачем существует эта часть системы

Без `Catalog` проекту некуда было бы складывать:

- источники;
- сырые и обогащённые новости;
- информацию о скачанных медиа.

Без `Shared` модули быстро начали бы дублировать:

- DTO-шки;
- enum-ы статусов;
- общие события;
- базовые сервисы вроде fingerprint/backoff policy.

## Ключевые классы, контракты и файлы

| Путь | Роль |
| --- | --- |
| `src/Modules/Shared/Domain/DTO/RawNewsData.php` | Общий DTO сырой новости |
| `src/Modules/Shared/Domain/DTO/EnrichedNewsData.php` | DTO после enrichment |
| `src/Modules/Shared/Domain/Enum/NewsStatus.php` | Статусы `processing/published/rejected` |
| `src/Modules/Shared/Domain/Events/*.php` | Межмодульные доменные события |
| `src/Modules/Shared/Domain/Contracts/NewsStore.php` | Shared-контракт на хранение новости |
| `src/Modules/Shared/Application/Services/FingerprintGenerator.php` | Генерация детерминированного fingerprint |
| `src/Modules/Shared/Application/Services/SourceRuntimeHealthPolicy.php` | Экспоненциальный backoff источников |
| `src/Modules/Catalog/Domain/Contracts/*.php` | Контракты репозиториев Catalog |
| `src/Modules/Catalog/Infrastructure/Persistence/*.php` | Реализации репозиториев на Eloquent/DB |
| `src/Modules/Catalog/Application/Listeners/*.php` | Реакция на межмодульные события |
| `src/Modules/Catalog/Application/Actions/PreloadNewsMediaAction.php` | Загрузка локальных медиа |

## Shared как "язык общения" между модулями

### `RawNewsData`

Это DTO, который выпускает Crawler и который принимает Intelligence.

Важные поля:

- `sourceId`
- `externalId`
- `title`
- `link`
- `content`
- `publishedAt`
- `language`
- `metadata`
- `imageUrl`
- `media`
- `fingerprint`
- `rawId`

DTO immutable-friendly: у него есть метод `with()` для создания копии с изменёнными полями.

### `EnrichedNewsData`

Это DTO финальной формы результата pipeline.

Поля:

- `rawId`
- `titleGenerated`
- `contentTranslated`
- `sentiment`
- `category`
- `tags`
- `importance`
- `status`
- `moderationReason`
- `fingerprint`

Он тоже поддерживает `with()` для copy-on-write сценария.

### `NewsStatus`

Всего три статуса:

- `processing`
- `published`
- `rejected`

Это хороший признак текущей зрелости проекта: state machine здесь простая и легко объяснимая.

## Shared services

### `FingerprintGenerator`

Один из ключевых сервисов проекта.

Приоритеты генерации fingerprint:

1. `sourceId + externalId`
2. `sourceId + normalizedTitle + minuteBucket`
3. `sourceId + normalizedLink + minuteBucket`

На выходе — `sha256`.

Почему minute bucket важен:

- если у RSS нет `externalId`, а заголовки и ссылки неидеальны, временное бакетирование снижает риск бесконечных дублей на уровне минутной гранулярности.

### `SourceRuntimeHealthPolicy`

Рассчитывает runtime backoff для источников после ошибок.

Что умеет:

- определять `nextRetryAt` из `retry_backoff_state` или `last_error_at + error_streak`;
- считать `backoff_minutes` экспоненциально;
- ограничивать максимум;
- определять, находится ли источник сейчас в backoff window;
- собирать `retry_backoff_state` для записи в БД.

Это shared service, потому что он нужен и framework-слою (`NewsCrawlCommand`, `CrawlerLog`), и persistence-обновлению статуса источника.

## `NewsStore` vs `NewsRepository`: почему два контракта

Это тонкая, но важная тема.

### `NewsStore`

Shared-контракт, который нужен Intelligence:

- `existsByFingerprint`
- `storeRaw`
- `storeEnriched`

### `NewsRepository`

Catalog-контракт, который расширяет `NewsStore` и добавляет Catalog-specific методы:

- `findIdByFingerprint`
- `getMediaUrls`

Практически это значит:

- Intelligence зависит от более абстрактного `NewsStore`;
- Catalog может иметь более богатый репозиторный API;
- одна и та же реализация `EloquentNewsRepository` закрывает оба интерфейса.

## `EloquentNewsRepository`

Это фактическая реализация persistence для news.

### `existsByFingerprint()`

Проверяет наличие записи по `raw_fingerprint`.

### `storeRaw()`

Использует `firstOrCreate` по `raw_fingerprint`.

При создании сохраняет:

- `source_id`
- `title_original`
- `content_original`
- `image_url`
- `media`
- `status = processing`
- `source_metadata`
- `published_at`

Здесь же в `source_metadata` складываются:

- `external_id`
- `link`
- `language`

То есть часть полей новости лежит в жёсткой схеме, а часть — в гибком JSONB.

### `storeEnriched()`

Обновляет уже существующую raw-запись по `rawId`:

- `title_generated`
- `content_translated`
- `sentiment_score`
- `tags`
- `is_important`
- `status`
- `moderation_reason`

То есть модель хранения двухфазная:

1. сначала raw insert в `processing`;
2. потом enrichment update.

### `getMediaUrls()`

Возвращает исходные `image_url` и `media` из `news_items`. Это потом нужно media-preload сценарию.

## Модели Catalog

### `Source`

Хранит:

- базовую конфигурацию источника;
- флаг `is_active`;
- служебные поля здоровья:
  - `retry_backoff_state`
  - `last_success_at`
  - `last_error_at`
  - `error_streak`

### `NewsItem`

Это центральная таблица данных публикаций.

Важные поля:

- original title/content;
- generated/translated fields;
- sentiment, tags, importance;
- status;
- `raw_fingerprint`;
- `source_metadata`;
- `published_at`;
- original media refs.

У неё есть relation `mediaAssets()`.

### `NewsMediaAsset`

Отдельная сущность для управления загрузкой локальных файлов:

- `slot`
- `position`
- `source_url`
- `local_disk`
- `local_path`
- `download_status`
- checksum, size, mime, errors

Это позволяет не перегружать `news_items` деталями локального storage lifecycle.

## Доменные события Shared

| Событие | Кто генерирует | Кто слушает |
| --- | --- | --- |
| `RawNewsCreated` | `ProcessNewsJob` | `ProcessRawNewsListener` |
| `NewsEnriched` | `NewsProcessingPipeline` | `QueueMediaPreloadListener` |
| `SourceFetchSucceeded` | `FeedFetcherAction` | `UpdateSourceStatusListener` |
| `SourceFetchFailed` | `FeedFetcherAction` | `UpdateSourceStatusListener` |

Эта схема важна: события служат клеем между модулями без жёсткой compile-time зависимости на конкретные сервисы друг друга.

## Обновление здоровья источника

`UpdateSourceStatusListener` реагирует на:

- `SourceFetchSucceeded`
- `SourceFetchFailed`

Дальше он делегирует работу в `SourceRepository`.

### `EloquentSourceRepository::updateSuccess()`

- ставит `last_success_at = now()`;
- сбрасывает `error_streak` в `0`;
- очищает `retry_backoff_state`.

### `EloquentSourceRepository::updateFailure()`

1. Открывает транзакцию.
2. Лочит запись `Source` через `lockForUpdate()`.
3. Увеличивает `error_streak`.
4. Строит новый `retry_backoff_state` через `SourceRuntimeHealthPolicy`.
5. Обновляет `last_error_at`, `error_streak`, `retry_backoff_state`.

Это хороший пример синхронизации operational state с помощью доменных событий.

## Как скачиваются медиа

### Шаг 1. Событие `NewsEnriched`

После сохранения enriched news pipeline генерирует `NewsEnriched(rawId)`.

### Шаг 2. Listener `QueueMediaPreloadListener`

Listener просто диспатчит `PreloadNewsMediaJob(rawId)`.

### Шаг 3. Job `PreloadNewsMediaJob`

Ставит обработку в очередь `media_tasks`.

### Шаг 4. `PreloadNewsMediaAction`

Action делает реальную работу:

1. Через `NewsRepository::getMediaUrls()` получает исходные media refs.
2. Через `NewsMediaAssetRepository::syncOriginalMedia()` синхронизирует desired asset set.
3. Получает список download candidates.
4. Для каждого кандидата:
   - скачивает файл через `Http::timeout(15)->retry(2, 200)->get(...)`;
   - определяет MIME и extension;
   - пишет файл на `Storage::disk(...)`;
   - записывает checksum, size, local path, status.
5. При ошибке помечает asset как `failed`.

Важно: скачивание медиа полностью асинхронно и отделено от основного pipeline сохранения новости.

## Почему у Delivery свой media resolver, хотя у Catalog есть media repository

Это хороший architectural nuance.

Catalog знает:

- как синхронизировать original assets;
- как скачивать и маркировать локальные копии;
- как хранить asset lifecycle.

Delivery знает:

- как собрать read-ready representation для клиента;
- как выбрать локальный URL или fallback на оригинальный.

Поэтому у Delivery есть свой контракт `NewsMediaResolver`, хотя таблица одна и та же.

## Какие данные здесь проходят и как они меняются

| Этап | Что происходит |
| --- | --- |
| `RawNewsData` | сохраняется как raw `news_items` |
| `EnrichedNewsData` | обновляет persisted news |
| `NewsEnriched` | инициирует media flow |
| original media refs | превращаются в `news_media_assets` |
| downloaded assets | получают local path, status, checksum |
| source failure/success | обновляют operational health fields `sources` |

## Какие есть ограничения, риски и edge cases

1. `storeRaw()` использует `firstOrCreate`, поэтому идемпотентность зависит и от fingerprint, и от DB unique constraint.
2. `title_generated` может так и остаться `null`, потому что anti-clickbait stage сейчас фактически пустой.
3. `news_media_assets` может быть в состоянии partial completion.
4. `NewsEnriched` содержит только `rawId`, а не весь DTO, поэтому downstream listeners должны при необходимости дочитывать данные сами.
5. `news_vectors` существует в схеме, но Catalog runtime его сейчас не использует.

## Что важно для Octane/RoadRunner и очередей

1. Catalog persistence используется и из queued pipeline, и из HTTP read flow.
2. Все репозитории должны быть stateless с точки зрения runtime процесса.
3. Media preload — отдельный async контур; нельзя предполагать его синхронное завершение.

## Что могут спросить на собеседовании

### Вопрос
Почему `NewsStore` вынесен в `Shared`, а не оставлен только в `Catalog`?

**Короткий ответ:** потому что это межмодульный контракт, через который Intelligence сохраняет новости, не зная деталей Catalog.

**Развёрнутый ответ:** `Shared` хранит общий язык взаимодействия. `NewsStore` описывает минимально необходимый API хранения для pipeline. `Catalog` уже даёт конкретную реализацию этого контракта через `EloquentNewsRepository`.

**На что обратить внимание:** это хороший пример direction of dependency.

### Вопрос
Как обеспечивается идемпотентность сохранения новостей?

**Короткий ответ:** через fingerprint + кодовую проверку + unique index в БД.

**Развёрнутый ответ:** `DeduplicateStep` заранее проверяет существование fingerprint, `storeRaw()` сохраняет запись через `firstOrCreate`, а таблица `news_items` дополнительно защищена уникальным индексом по `raw_fingerprint`.

**На что обратить внимание:** подчеркни, что DB-level гарантия важнее application-level проверки при гонках.

### Вопрос
Почему медиа вынесены в отдельную таблицу, а не просто лежат целиком в `news_items`?

**Короткий ответ:** потому что lifecycle загрузки локальных файлов сложнее, чем просто хранение исходного URL.

**Развёрнутый ответ:** нужны статусы загрузки, checksum, размер, локальный путь, ошибка скачивания, slot и position. Всё это не стоит смешивать с основной таблицей новостей.

**На что обратить внимание:** отдельно скажи про асинхронность `media_tasks`.

## Куда смотреть в коде

- `src/Modules/Shared/Domain/DTO/RawNewsData.php`
- `src/Modules/Shared/Domain/DTO/EnrichedNewsData.php`
- `src/Modules/Shared/Domain/Enum/NewsStatus.php`
- `src/Modules/Shared/Domain/Events/*.php`
- `src/Modules/Shared/Domain/Contracts/NewsStore.php`
- `src/Modules/Shared/Application/Services/FingerprintGenerator.php`
- `src/Modules/Shared/Application/Services/SourceRuntimeHealthPolicy.php`
- `src/Modules/Catalog/Domain/Contracts/NewsRepository.php`
- `src/Modules/Catalog/Domain/Contracts/SourceRepository.php`
- `src/Modules/Catalog/Domain/Contracts/NewsMediaAssetRepository.php`
- `src/Modules/Catalog/Infrastructure/Persistence/EloquentNewsRepository.php`
- `src/Modules/Catalog/Infrastructure/Persistence/EloquentSourceRepository.php`
- `src/Modules/Catalog/Infrastructure/Persistence/EloquentNewsMediaAssetRepository.php`
- `src/Modules/Catalog/Application/Listeners/UpdateSourceStatusListener.php`
- `src/Modules/Catalog/Application/Listeners/QueueMediaPreloadListener.php`
- `src/Modules/Catalog/Application/Actions/PreloadNewsMediaAction.php`

## Связанные документы

- [05. Модуль Intelligence: Pipeline Обработки Сырой Новости](05_intelligence.md) — кто именно сохраняет `EnrichedNewsData`
- [11. Модель Данных, Таблицы и Индексы](11_data_model_and_indexes.md) — физическая схема таблиц
- [12. События, Очереди и Messaging](12_events_queues_and_messaging.md) — вся event/queue topology
