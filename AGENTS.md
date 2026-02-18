# AGENTS.md

## Правила памяти проекта

- Каноническая память проекта хранится в `docs/PROJECT_MEMORY.md`.
- Перед началом реализации обязательно прочитать `docs/PROJECT_MEMORY.md`.
- После каждого значимого изменения (архитектура, контракты, инфра, процессы, backlog) обновлять `docs/PROJECT_MEMORY.md`.

## Правила карты зависимостей

- Карта зависимостей бизнес-логики хранится в `docs/BUSINESS_LOGIC_DEPENDENCIES.md`.
- Файл `docs/BUSINESS_LOGIC_DEPENDENCIES.md` генерируемый, не поддерживается вручную.
- После изменений в классах `app/` и `src/Modules/` нужно перегенерировать карту:
    - `make docs-deps`
- Перед архитектурными изменениями сверяться с `docs/BUSINESS_LOGIC_DEPENDENCIES.md`.

## Правила валидации

- После любых изменений в бизнес-логике (`app/`, `src/Modules/`) или архитектуре обязательно запускать CI-тесты:
    - `make ci-check`
- Коммитить код можно только при успешном прохождении всех проверок.

## Правила работы с Docker

- **ВСЕ команды разработки (artisan, composer, pest, phpstan, pint) должны выполняться внутри Docker-контейнера `app`**.
    - ❌ `php artisan migrate` (на хосте)
    - ❌ `./vendor/bin/pest` (на хосте)
    - ✅ `docker compose exec -it app php artisan migrate`
    - ✅ `docker compose exec -it app ./vendor/bin/pest`
