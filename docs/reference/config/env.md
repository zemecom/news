# Env Reference

Этот файл нужен, чтобы не разбирать `.env.example` как археологию. Ниже собраны именно те переменные, которые чаще всего важны при локальной разработке и отладке проекта.

## 1. Базовый принцип

Стартовая точка всегда одна:

```bash
cp .env.example .env
```

Обычно это уже делает `make setup-local`.

Если ты не понимаешь переменную, не меняй её "наугад". Для этого проекта `.env` влияет не только на Laravel, но и на Docker ports, RabbitMQ, crawler security, Octane и debug tooling.

## 2. Самый важный минимум для локальной среды

| Переменная | Типичное значение | Зачем нужна |
| --- | --- | --- |
| `APP_ENV` | `local` | включает локальное поведение |
| `APP_DEBUG` | `true` | подробные ошибки |
| `APP_URL` | `http://localhost:8080` | база для URL и storage links |
| `APP_PORT` | `8080` | внешний HTTP-порт приложения |
| `VITE_PORT` | `5173` | внешний порт HMR |
| `DB_*` | `postgres / 5432 / smartnews` | подключение к PostgreSQL |
| `REDIS_*` | `redis / 6379` | Redis |
| `QUEUE_CONNECTION` | `rabbitmq` | backend для Laravel queue |
| `RABBITMQ_*` | `rabbitmq / 5672 / smartnews` | подключение к RabbitMQ |
| `FILESYSTEM_DISK` | `local` или `public` | storage-диск для файлов |
| `OCTANE_SERVER` | `roadrunner` | HTTP runtime |

Если ты просто хочешь запустить проект локально, чаще всего достаточно не трогать эти значения.

## 3. Группы переменных

### Приложение и порты

| Переменная | Что контролирует | Когда менять |
| --- | --- | --- |
| `APP_NAME` | имя приложения | почти никогда |
| `APP_URL` | базовый URL | если меняешь порт/домен |
| `APP_PORT` | проброшенный HTTP-порт | если `8080` занят |
| `APP_TIMEZONE` | timezone приложения | если проект запускается не в московском поясе |
| `DOCKER_BUILD_TARGET` | `local` или `production` | обычно не трогать при обычной разработке |
| `VITE_PORT` | HMR-порт | если `5173` занят |

### База, Redis и RabbitMQ

| Переменная | Что контролирует | Когда менять |
| --- | --- | --- |
| `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | PostgreSQL | если меняешь compose-схему или внешнюю БД |
| `REDIS_HOST`, `REDIS_PORT` | Redis | если Redis внешний или порт занят |
| `QUEUE_CONNECTION` | queue backend | обычно должен оставаться `rabbitmq` |
| `RABBITMQ_HOST`, `RABBITMQ_PORT`, `RABBITMQ_USER`, `RABBITMQ_PASSWORD`, `RABBITMQ_VHOST` | RabbitMQ | если меняешь локальные креды или внешний инстанс |
| `RABBITMQ_QUEUE_*` | exchange/routing для Laravel Queue backend | менять только если понимаешь transport-контур |
| `RABBITMQ_RETRY_AFTER` | timeout/requeue поведение | менять очень осторожно, потому что связано с worker timeout |

### Порты проброса наружу

| Переменная | Что делает |
| --- | --- |
| `POSTGRES_BIND` | на какой host:port пробросить PostgreSQL |
| `REDIS_BIND` | на какой host:port пробросить Redis |
| `RABBITMQ_BIND` | на какой host:port пробросить AMQP |
| `RABBITMQ_MANAGEMENT_BIND` | на какой host:port пробросить RabbitMQ UI |

Обычно дефолт `127.0.0.1:...` правильный и безопасный для локалки.

### Crawler и security policy

| Переменная | Что контролирует | Когда менять |
| --- | --- | --- |
| `TELEGRAM_FETCH_LIMIT` | максимум сообщений за проход | если хочешь глубже/мельче fetch |
| `CRAWLER_ALLOWLIST` | глобальный allowlist хостов | если добавляешь новый доверенный источник |
| `CRAWLER_RSS_ALLOWLIST` | allowlist только для RSS | если crawler блокирует нужный RSS |
| `CRAWLER_TELEGRAM_ALLOWLIST` | allowlist для Telegram | по умолчанию `t.me` |
| `CRAWLER_ALLOWED_SOURCE_SCHEMES` | допустимые схемы источников | почти всегда `https` |
| `CRAWLER_ALLOWED_URL_SCHEMES` | допустимые схемы ссылок внутри контента | обычно `http,https` |
| `CRAWLER_DENY_PRIVATE_HOSTS` | защита от private/local hosts | почти всегда должно оставаться `true` |
| `CRAWLER_SANITIZE_MAX_TEXT_LENGTH` | лимит текста после очистки | если тестируешь очень длинные материалы |
| `CRAWLER_SANITIZE_MAX_CATEGORY_LENGTH` | лимит длины категории | редко меняется |
| `CRAWLER_HEALTH_BACKOFF_*` | политика backoff для источников | если настраиваешь реакцию на ошибки |

Если crawler "неожиданно" не ходит в источник, первым делом смотри именно сюда.

### Dev/debug

| Переменная | Что контролирует | Когда включать |
| --- | --- | --- |
| `WITH_XDEBUG` | Xdebug в контейнере `app` | когда дебажишь HTTP |
| `WITH_XDEBUG_WORKER` | Xdebug в контейнере `worker` | когда дебажишь jobs/listeners |
| `XDEBUG_MODE` | режим Xdebug | обычно `debug` |
| `XDEBUG_CLIENT_HOST` | куда Xdebug коннектится | зависит от IDE/OS |
| `XDEBUG_CLIENT_PORT` | порт Xdebug | если в IDE другой порт |
| `DEBUGBAR_ENABLED` | включает Laravel Debugbar | когда нужен быстрый локальный анализ request |
| `TELESCOPE_ENABLED` | включает Telescope | почти всегда полезно локально |
| `ADMIN_AUTO_LOGIN` | авто-логин в админке в local | удобно для ручной диагностики |

### Интеграции и внешние ключи

| Переменная | Что значит |
| --- | --- |
| `LLM_PROVIDER` | какой провайдер считать основным |
| `LLM_FALLBACK_PROVIDER` | какой провайдер использовать как fallback |
| `LLM_ANALYSIS_CACHE_STORE` | cache-store для успешных AI-ответов по fingerprint/model/reasoning/version |
| `LLM_ANALYSIS_CACHE_TTL_SECONDS` | TTL кэша успешных AI-ответов |
| `LLM_CHATGPT_CODEX_MODEL` | модель для ChatGPT/Codex CLI |
| `LLM_CHATGPT_CODEX_REASONING_EFFORT` | уровень reasoning effort для Codex; пусто = default модели |
| `LLM_CHATGPT_CODEX_TIMEOUT_SECONDS` | timeout одного Codex exec вызова |
| `LLM_CHATGPT_CODEX_APP_SERVER_TIMEOUT_SECONDS` | timeout одного Codex app-server запроса |
| `LLM_CHATGPT_CODEX_CONCURRENCY_CACHE_STORE` | cache-store для concurrency lock/semaphore |
| `LLM_CHATGPT_CODEX_RELEASE_DELAY_SECONDS` | задержка retry, если provider slot занят |
| `CODEX_BINARY` | путь к бинарю `codex` внутри runtime |
| `CODEX_HOME_BASE` | базовый путь хранения auth/runtime состояния Codex |
| `LLM_CHATGPT_SCRATCH_DIR` | scratch-dir для non-interactive `codex exec` |
| `OPENAI_API_KEY` | ключ OpenAI |
| `ANTHROPIC_API_KEY` | ключ Anthropic |
| `DEEPSEEK_API_KEY` | ключ DeepSeek |
| `TELEGRAM_BOT_TOKEN` | токен бота |
| `TELEGRAM_WEBHOOK_SECRET` | секрет webhook |

Сейчас Intelligence в основном эвристический, поэтому для базового знакомства эти ключи не обязательны.
Для нового `chatgpt_codex` провайдера важнее не API-ключ, а корректный `codex` runtime и сохраненный auth state в `CODEX_HOME_BASE`.

## 4. Что безопасно не трогать в начале

Если ты только знакомишься с проектом, не спеши менять:

- `QUEUE_CONNECTION`
- `RABBITMQ_QUEUE_EXCHANGE`
- `RABBITMQ_QUEUE_ROUTING_KEY`
- `RABBITMQ_RETRY_AFTER`
- `CRAWLER_DENY_PRIVATE_HOSTS`
- `OCTANE_SERVER`

Это те настройки, которые влияют на фундаментальный runtime-поток.

## 5. Типовые симптомы и где искать причину

| Симптом | Что проверить первым |
| --- | --- |
| приложение не открывается | `APP_PORT`, `make logs`, контейнер `app` |
| `/health/ready` возвращает `503` | `DB_*`, `REDIS_*`, `RABBITMQ_*`, состояние контейнеров |
| crawler не может сходить во внешний источник | `CRAWLER_*ALLOWLIST`, схемы URL, `CRAWLER_DENY_PRIVATE_HOSTS` |
| RabbitMQ requeue/timeout ведут себя странно | `RABBITMQ_RETRY_AFTER` и worker timeout |
| Debugbar не видно | `DEBUGBAR_ENABLED=true` |
| Telescope недоступен | `TELESCOPE_ENABLED=true` и `APP_ENV=local` |
| Xdebug не цепляется | `WITH_XDEBUG`, `XDEBUG_MODE`, `XDEBUG_CLIENT_HOST`, `XDEBUG_CLIENT_PORT` |
| admin-панель просит логин | `ADMIN_AUTO_LOGIN` или seeded users |

## 6. Практические профили

### Обычная локальная разработка

Оставь `.env.example` почти как есть. В таком режиме `worker` не стартует автоматически и это нормально: локалка экономит память, а очереди можно поднять отдельно через `docker compose --profile queue up -d worker`.

### Локальная отладка HTTP через IDE

Проверь:

```dotenv
WITH_XDEBUG=1
XDEBUG_MODE=debug
XDEBUG_CLIENT_HOST=host.docker.internal
XDEBUG_CLIENT_PORT=9003
```

После изменения `WITH_XDEBUG` пересобери контейнер:

```bash
docker compose up -d --build app
```

### Локальная отладка очередей

Проверь:

```dotenv
WITH_XDEBUG_WORKER=1
XDEBUG_MODE=debug
```

И подними сам `worker` профилем:

```bash
docker compose --profile queue up -d worker
```

### Быстрый вход в admin-панель

Проверь:

```dotenv
ADMIN_AUTO_LOGIN=true
```

Только помни: это локальный convenience-флаг, а не production auth strategy.

## 7. Где смотреть source of truth

Если переменная кажется подозрительной, смотри:

- `.env.example`
- `config/app.php`
- `config/database.php`
- `config/queue.php`
- `config/messaging.php`
- `config/crawler.php`
- `config/octane.php`
- `docker-compose.yml`

Этот документ покрывает самые важные project-specific настройки, а не все возможные env-переменные Laravel и сторонних пакетов.
