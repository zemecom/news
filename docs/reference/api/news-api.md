# News API Reference

Этот файл описывает API так, как с ним удобно знакомиться руками: какие endpoints есть, какие параметры они принимают и какой JSON реально возвращают.

## 1. Основные endpoints

| Метод и путь | Назначение | Где смотреть реализацию |
| --- | --- | --- |
| `GET /api/news` | лента новостей | `NewsController@index` |
| `GET /api/news/{id}` | карточка одной новости | `NewsController@show` |
| `GET /api/sources` | публичный список источников | `NewsController@sources` |
| `GET /api/admin/sources` | расширенный admin-список источников | `Api\Admin\SourceController@index` |

## 2. `GET /api/news`

### Query-параметры

| Параметр | Тип | Что делает |
| --- | --- | --- |
| `cursor` | `string` | курсор для следующей/предыдущей страницы |
| `per_page` | `integer` | размер страницы, от `1` до `100` |
| `category` | `string` | фильтр по тегу категории |
| `sentiment_min` | `integer` | минимальная тональность, диапазон `-10..10` |
| `sentiment_max` | `integer` | максимальная тональность, диапазон `-10..10` |
| `important` | `boolean` | фильтр по важности |
| `date_from` | `date` | нижняя граница `published_at` |
| `date_to` | `date` | верхняя граница `published_at` |
| `q` | `string` | поиск по заголовкам |
| `source_id` | `integer` | фильтр по источнику |

### Пример запроса

```bash
curl "http://localhost:8080/api/news?per_page=2&category=laravel&important=0"
```

### Пример ответа

```json
{
  "data": [
    {
      "id": 1,
      "source_id": 1,
      "title_original": "Laravel 12 Released with New Features",
      "title_generated": null,
      "content_original": "Laravel 12 introduces several new features and improvements.",
      "content_translated": null,
      "image_url": null,
      "image_url_original": null,
      "image_url_local": null,
      "media": [],
      "media_original": [],
      "media_local": [],
      "sentiment": 4,
      "tags": ["laravel"],
      "important": false,
      "status": "published",
      "published_at": "2026-03-07T10:00:00+03:00"
    }
  ],
  "meta": {
    "per_page": 2,
    "next_cursor": null,
    "prev_cursor": null,
    "total": 1
  }
}
```

### Что важно понимать

1. Публичная лента отдаёт только новости со статусом `published`.
2. `total` считается отдельным запросом и не является частью cursor paginator.
3. `image_url` и `media` уже могут быть подменены на локальные storage-URL, если медиа были скачаны заранее.

## 3. `GET /api/news/{id}`

### Пример запроса

```bash
curl "http://localhost:8080/api/news/1"
```

### Пример ответа

```json
{
  "data": {
    "id": 1,
    "source_id": 1,
    "title_original": "Laravel 12 Released with New Features",
    "title_generated": null,
    "content_original": "Laravel 12 introduces several new features and improvements.",
    "content_translated": null,
    "image_url": null,
    "image_url_original": null,
    "image_url_local": null,
    "media": [],
    "media_original": [],
    "media_local": [],
    "raw_fingerprint": "seed-fp-1",
    "status": "published",
    "published_at": "2026-03-07T10:00:00+03:00"
  }
}
```

### Если новости нет

API вернёт:

```json
{
  "message": "News item not found."
}
```

с HTTP-кодом `404`.

## 4. `GET /api/sources`

### Что возвращает

Публичный endpoint отдаёт только безопасный минимальный набор полей:

```json
{
  "data": [
    {
      "id": 1,
      "name": "Hacker News"
    }
  ]
}
```

Это не operational endpoint. Он не показывает `url`, `error_streak`, `last_success_at` и другие служебные поля.

## 5. `GET /api/admin/sources`

Этот endpoint защищён middleware:

- `auth`
- `role.admin`

Поэтому анонимный `curl` здесь не является обычным сценарием.

Что важно знать:

1. без пользователя ты получишь `401`;
2. с обычным пользователем ты получишь `403`;
3. с admin-пользователем ты увидишь расширенный список источников и их operational-состояние.

Для локального знакомства с admin surface проще использовать:

- `/admin` через Filament;
- или seeded admin-пользователя `admin@example.com` / `password`.

## 6. Основные коды ответа

| Код | Когда бывает |
| --- | --- |
| `200` | успешный запрос |
| `401` | нет аутентификации для admin endpoint |
| `403` | пользователь есть, но роль не admin |
| `404` | новость с таким id не найдена |
| `422` | ошибка валидации query-параметров |

## 7. Где в коде смотреть source of truth

Если хочешь не просто пользоваться API, а понять его устройство, смотри:

- `routes/api.php`
- `app/Http/Controllers/Api/NewsController.php`
- `app/Http/Requests/Api/NewsIndexRequest.php`
- `src/Modules/Delivery/Domain/DTO/NewsFeedFilters.php`
- `src/Modules/Delivery/Application/Actions/ListNewsAction.php`
- `src/Modules/Delivery/Infrastructure/Persistence/EloquentNewsFeedReader.php`
- `tests/Feature/Api/NewsApiTest.php`

## 8. Что чаще всего путают новички

1. `GET /api/news` не запускает crawler и не строит новости на лету. Он читает уже подготовленные записи из БД.
2. `image_url` в ответе не всегда равен оригинальному URL источника. Delivery может подставить локальную ссылку.
3. `GET /api/admin/sources` — это не "ещё один публичный список", а operational admin read model.
