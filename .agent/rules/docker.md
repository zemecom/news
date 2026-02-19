---
description: Ensure all commands are executed within the Docker app container.
---

# Docker Execution Rules

**Docker**: ВСЕ без исключения команды разработки (artisan, composer, php, npm, tests, make) должны запускаться СТРОГО внутри Docker контейнера `app` (через `docker compose exec app ...`), если иное явно не оговорено пользователем. НИКОГДА не запускай их на хосте (напрямую из корня проекта)!
