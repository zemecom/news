# 03. Модуль Delivery: Доставка контента

Модуль `Delivery` (Доставка) — это первая остановка для нашего запроса внутри модульного монолита, если мы говорим о реальной бизнес-логике.
Официант (`app/` Controller) берет запрос, передает его в Delivery, а Delivery собирает нужные данные из `Catalog` и отдает официанту, чтобы тот вернул их клиенту.

Всё, что связано с "отдать списки новостей или источников", живет в **`src/Modules/Delivery`**.

## Связь Controller -> Action
Давай посмотрим на контроллер `app/Http/Controllers/Api/NewsController.php`, который отвечает на AJAX-запрос за списком новостей (`GET /api/news`):

```php
public function index(NewsIndexRequest $request, ListNewsAction $action): JsonResponse
{
    // 1. DTO: Строго типизированные фильтры
    $filters = new NewsFeedFilters(
        sourceId: $request->integer('source_id') ?: null,
        limit: $request->integer('limit', 50),
        cursor: $request->string('cursor')->toString()
    );
    
    // 2. Action: Ядро сценария из Delivery
    $result = $action->execute($filters);

    return response()->json($result);
}
```
Он использует `NewsIndexRequest` (FormRequest Laravel), чтобы проверить, что `limit` это точно число, а не строка. А затем конструирует объект `NewsFeedFilters`.

### Зачем нужен DTO `NewsFeedFilters`?
Класс `NewsFeedFilters` (лежит в `Delivery/Domain/DTO/`) — это Data Transfer Object.
Зачем? Представь, что мы передавали бы в Action обычный массив: `$action->execute(['limit' => 50])`. Что будет, если фронтендер опечатается и пришлет `limt=50`? Мы даже не узнаем об этом, пока не упадет SQL-запрос. 
DTO заставляет нас строго определить, какие поля мы ожидаем (через конструктор), и если поля нет — PHP упадет с понятной ошибкой еще на этапе контроллера.

## Гексагональная архитектура Delivery
Запрос попал в `ListNewsAction` (слой `Application`). Что там происходит?

### 1. Application: `ListNewsAction`
Открой `src/Modules/Delivery/Application/Actions/ListNewsAction.php`. 
В его конструкторе инжектится зависимость (DI):
`public function __construct(private NewsFeedReader $reader) {}`

Класс делает простую вещь: берет наши фильтры DTO и вызывает `$this->reader->getNewsFeed($filters)`. Метод возвращает `CursorPaginator`, который экшен отдает обратно в контроллер. ВСЁ!

### 2. Domain: Контракты (Интерфейсы)
Открой `src/Modules/Delivery/Domain/Contracts/NewsFeedReader.php`.
Здесь **НЕТ** кода. Только описание: `public function getNewsFeed(NewsFeedFilters $filters): CursorPaginator;`.
Это контракт. `ListNewsAction` вообще не знает, база данных это, или текстовый файл. Он просто знает, что у класса будут те новости, которые нужно. Это классический *Inversion of Control* (IoC) — слой Application зависит от контрактов, а не от конкретных реализаций.

### 3. Infrastructure: `EloquentNewsFeedReader`
Но ведь где-то новости лежат! Мы используем Eloquent (модели БД Laravel).
Поэтому в папке `Delivery/Infrastructure/Persistence/` лежит `EloquentNewsFeedReader.php`.
Именно в нём собирается мощный "билдер" запросов (Builder):
```php
$query = NewsItem::query()
    ->where('status', NewsStatus::ENRICHED->value)
    ->orderByDesc('published_at');

if ($filters->sourceId) {
    $query->where('source_id', $filters->sourceId);
}
```
Этот класс реализует интерфейс `NewsFeedReader`. 

**Как Laravel понимает, какой класс дать?**
Для этого существует `DeliveryServiceProvider`. В нём прописано:
```php
$this->app->bind(NewsFeedReader::class, EloquentNewsFeedReader::class);
```
Когда `ListNewsAction` просит `NewsFeedReader`, Laravel заглядывает в этот маппинг и подсовывает ему `EloquentNewsFeedReader`. Гениально просто.

---

## 4. Разрешение ссылок на картинки (`NewsMediaResolver`)
Когда Eloquent извлекает данные из таблицы `news_items`, в колонках мы храним ссылки. 
Но помнишь, Crawler качает картинки (media) к нам на жесткий диск?
У нас стоит задача: если картинка скачана, мы не хотим отдавать клиенту оригинальную ссылку (вдруг удалят?), мы хотим отдавать *нашу локальную* ссылку.

Для этого в Delivery существует контракт `NewsMediaResolver`.
При получении модели новости (в ридере), данные прогоняются через `DbNewsMediaResolver` (он лежит в Infrastructure). Этот класс лезет в таблицу `news_media_assets` по ID новости и `source_url`. Если находит статус "Успешно скачан", он заменяет в JSON'е оригинальную ссылку на ссылку вида `http://localhost:8080/storage/media/file.jpg`. Если картинка ещё качается воркером — он оставляет оригинальный URL.

В Delivery мы только читаем. Кто эти данные кладёт и обрабатывает? Переходим к `04_crawler.md`!
