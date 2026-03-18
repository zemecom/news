#!/bin/sh
set -eu

PROJECT_RR_PATH="/app/rr"
RUNTIME_RR_DIR="/tmp/roadrunner-bin"
RUNTIME_RR_PATH="${RUNTIME_RR_DIR}/rr"

mkdir -p "${RUNTIME_RR_DIR}"
mkdir -p "${CODEX_HOME_BASE:-/home/www-data/.codex/providers}"

sh docker/bin/sync-ai-provider-state.sh

# Move project-local RoadRunner binary out of mounted project dir.
if [ -f "${PROJECT_RR_PATH}" ]; then
    cp "${PROJECT_RR_PATH}" "${RUNTIME_RR_PATH}"
    chmod 0755 "${RUNTIME_RR_PATH}"
    rm -f "${PROJECT_RR_PATH}"
fi

# If cached binary is broken or missing, download a fresh one.
if [ -x "${RUNTIME_RR_PATH}" ] && ! "${RUNTIME_RR_PATH}" --version >/dev/null 2>&1; then
    rm -f "${RUNTIME_RR_PATH}"
fi

if [ ! -x "${RUNTIME_RR_PATH}" ]; then
    php ./vendor/bin/rr get-binary --no-config --location "${RUNTIME_RR_DIR}" --no-interaction
    chmod 0755 "${RUNTIME_RR_PATH}"
fi

export PATH="${RUNTIME_RR_DIR}:${PATH}"

exec php artisan octane:start \
    --server=roadrunner \
    --rr-config=.rr.yaml \
    --host=0.0.0.0 \
    --rpc-port=6001 \
    --port=8000 \
    --workers="${OCTANE_WORKERS:-1}" \
    "$@"
