# 09. Как добавить новую фичу (How To)

Читать теорию легко, но когда шеф ставит задачу: *"Добавь-ка нам счетчик просмотров новостей"*, можно растеряться: куда писать код? В Delivery? В Shared? Или в Catalog?

Этот гайд — твоя шпаргалка по внедрению фич в нашу слоистую архитектуру (Hexagonal Modular Monolith).

---

## Задача: Считать количество отображений (показов) новости
**Требование**: Каждый раз, когда новость отдается через API (`GET /api/news`), мы хотим увеличивать счетчик `views_count` в базе данных.

Давай декомпозируем задачу:
1. "Отдается через API" — это модуль `Delivery`.
2. В базе данных есть новость — это хранилище модуля `Catalog`.
Но `Delivery` не имеет права напрямую ходить в БД и делать `UPDATE news_items SET views_count...`. Что делать? Мы будем использовать **Domain Events** (События).

### Шаг 1: Добавляем Событие в Shared
*(Модуль: `Shared` | Слой: `Domain`)*
Нам нужно создать общее событие, которое поймут оба модуля.
Создаем файл: `src/Modules/Shared/Domain/Events/NewsDelivered.php`.
```php
class NewsDelivered
{
    public function __construct(
        public readonly int $newsId // Или externalId/fingerprint
    ) {}
}
```

### Шаг 2: Выбрасываем событие из Delivery
*(Модуль: `Delivery` | Слой: `Application`)*
Кто формирует список новостей? `ListNewsAction`.
Открываем `src/Modules/Delivery/Application/Actions/ListNewsAction.php`.
После того как ридер достал новости из интерфейса `NewsFeedReader`, мы в цикле (или кучей) делаем:
```php
foreach ($result->items() as $item) {
    event(new NewsDelivered($item->id));
}
```
Всё! `Delivery` сделал свою работу. Он просто "выкрикнул" во вселенную: "Эй, я только что показал новость X". Ему неважно, услышит ли кто-то этот крик или нет.

### Шаг 3: Слушаем событие в Catalog
*(Модуль: `Catalog` | Слой: `Application`)*
Теперь нам нужно, чтобы база данных услышала этот крик и обновила счетчик.
Создаем слушателя: `src/Modules/Catalog/Application/Listeners/IncrementNewsViewsListener.php`.
```php
class IncrementNewsViewsListener
{
    public function __construct(
        private NewsRepository $repository
    ) {}

    public function handle(NewsDelivered $event): void
    {
        $this->repository->incrementViews($event->newsId);
    }
}
```
**Важно:** Не забудь зарегистрировать эту связку Event -> Listener в сервис-провайдере каталога (`CatalogServiceProvider` или `EventServiceProvider`, если он выделен внутри модуля).

### Шаг 4: Контракт и Инфраструктура в Catalog
Ты заметил, что мы вызвали метод `$this->repository->incrementViews($id)`? Но в контракте `NewsRepository` такого метода сейчас нет!

*(Модуль: `Catalog` | Слой: `Domain`)*
Открываем `src/Modules/Catalog/Domain/Contracts/NewsRepository.php` и добавляем метод в интерфейс:
```php
public function incrementViews(int $id): void;
```

*(Модуль: `Catalog` | Слой: `Infrastructure`)*
Теперь идем в реализацию: `EloquentNewsRepository.php` и пишем реальный SQL/Eloquent код:
```php
public function incrementViews(int $id): void
{
    NewsItem::where('id', $id)->increment('views_count');
}
```
*(Конечно же, не забудь сделать миграцию для добавления колонки `views_count`).*

---

## Почему мы сделали именно так, а не просто `NewsItem::increment` в контроллере?

1. **Независимость (Decoupling):** Завтра, модуль `Intelligence` захочет обучать свою нейросеть (AI) на самых просматриваемых новостях. Искусственный интеллект просто подпишется на то же самое событие `NewsDelivered` в своем Listener'е и начнет собирать статистику! Нам не придется менять ни строчки кода в Delivery или Catalog. Одно событие — куча разных реакций в разных модулях.
2. **Асинхронность:** Если обновление БД начнет тормозить, мы просто добавим слушателю `IncrementNewsViewsListener` интерфейс `ShouldQueue`, и Laravel начнет обновлять счетчики в фоновых воркерах RabbitMQ. HTTP-ответ клиенту останется таким же мгновенным.
3. **Безопасность (Testability):** Мы можем написать Unit-тест на Action из Delivery, просто проверяя, что `Event::fake()` перехватил `NewsDelivered`. Нам даже не потребуется для этого теста база данных.

## Золотые правила:
1. Если фича требует изменения БД — всегда меняем контракт в `Domain`, потом реализацию в `Infrastructure`.
2. Если фича затрагивает два модуля — они общаются строго через `Events` (события) или `DTO` (из `Shared`).
3. В папке `app/` лежат только "рубильники" (Контроллеры/Команды), которые дергают классы слоя `Application` соответствующего модуля.

Добро пожаловать в проект!
