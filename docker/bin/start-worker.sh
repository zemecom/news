#!/bin/sh
set -eu

mkdir -p "${CODEX_HOME_BASE:-/home/www-data/.codex/providers}"

sh docker/bin/sync-ai-provider-state.sh
php artisan news:messaging:setup

QUEUE_EXCHANGE="${RABBITMQ_QUEUE_EXCHANGE:-news.jobs}"
QUEUE_EXCHANGE_TYPE="${RABBITMQ_QUEUE_EXCHANGE_TYPE:-direct}"
WORKER_QUEUES="${WORKER_QUEUES:-crawler_tasks,intelligence_tasks,media_tasks}"

php artisan rabbitmq:exchange-declare "${QUEUE_EXCHANGE}" rabbitmq --type="${QUEUE_EXCHANGE_TYPE}" --durable=1 --auto-delete=0 --quiet

for queue in $(echo "${WORKER_QUEUES}" | tr ',' ' '); do
    php artisan rabbitmq:queue-declare "${queue}" rabbitmq --durable=1 --auto-delete=0 --quiet
    php artisan rabbitmq:queue-bind "${queue}" "${QUEUE_EXCHANGE}" rabbitmq --routing-key="${queue}" --quiet
done

exec php artisan queue:work \
    --queue="${WORKER_QUEUES}" \
    --tries=3 \
    --sleep=1 \
    --timeout=120 \
    "$@"
