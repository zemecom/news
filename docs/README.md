# Документация SmartNews

Этот каталог теперь разложен не по историческим артефактам, а по назначению. Ниже короткая карта, чтобы не искать нужный файл по памяти.

## Куда идти сначала

- Если ты впервые в проекте: [start/junior-onboarding.md](start/junior-onboarding.md)
- Если хочешь учиться на проекте пошагово: [start/learning-path.md](start/learning-path.md)
- Если нужен API и примеры JSON: [reference/api/news-api.md](reference/api/news-api.md)
- Если нужно понять runtime и процессы: [guides/runtime-operations.md](guides/runtime-operations.md)
- Если нужно разобраться с архитектурой целиком: [architecture/00-overview.md](architecture/00-overview.md)
- Если готовишься к собеседованию по проекту: [interview/question-bank.md](interview/question-bank.md)

## Как устроен каталог

- `start/` — практический вход в проект и учебный маршрут для джуна.
- `architecture/` — подробный разбор модулей, потоков, данных, очередей, тестирования и компромиссов.
- `guides/` — операционные и практические playbook-документы.
- `reference/` — справочники по API и конфигурации.
- `interview/` — банк вопросов и глоссарий для собеседований и быстрого повторения.

## Отдельные канонические файлы

- [PROJECT_MEMORY.md](PROJECT_MEMORY.md) — живая память проекта: важные решения, текущее состояние, backlog и ограничения.
- [PROJECT_STRUCTURE.md](PROJECT_STRUCTURE.md) — сгенерированная карта файлов и каталогов.
- [PROJECT_INTERFACE.md](PROJECT_INTERFACE.md) — сгенерированная карта интерфейсов и зависимостей классов.

Эти три файла остаются в корне `docs/`, потому что на них завязаны правила проекта и совместимость агентных workflow.

## PDF и артефакты

- Сборка PDF: `scripts/docs-pdf/build.sh`
- Готовые PDF: `artifacts/docs/`

PDF-артефакты вынесены из `docs/`, потому что это результат сборки, а не исходная документация.
