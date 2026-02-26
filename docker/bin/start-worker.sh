#!/bin/sh
set -eu

for queue in crawler_tasks intelligence_tasks media_tasks; do
    php artisan rabbitmq:queue-declare "${queue}" rabbitmq --durable=1 --auto-delete=0 --quiet
done

exec php artisan queue:work \
    --queue=crawler_tasks,intelligence_tasks,media_tasks \
    --tries=3 \
    --sleep=1 \
    --timeout=120 \
    "$@"
