# AGENTS.md

## Правила памяти проекта

- Каноническая память проекта хранится в `docs/PROJECT_MEMORY.md`.
- Перед началом реализации обязательно прочитать `docs/PROJECT_MEMORY.md`.
- После каждого значимого изменения (архитектура, контракты, инфра, процессы, backlog) обновлять `docs/PROJECT_MEMORY.md`.

## Правила карты зависимостей

- Карта зависимостей бизнес-логики хранится в `docs/BUSINESS_LOGIC_DEPENDENCIES.md`.
- Файл `docs/BUSINESS_LOGIC_DEPENDENCIES.md` генерируемый, не поддерживается вручную.
- После изменений в классах `app/` и `src/Modules/` нужно перегенерировать карту:
  - `php scripts/generate_business_deps.php`
- Перед архитектурными изменениями сверяться с `docs/BUSINESS_LOGIC_DEPENDENCIES.md`.
