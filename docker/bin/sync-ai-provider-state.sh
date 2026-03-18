#!/bin/sh
set -eu

CODEX_HOME_BASE_PATH="${CODEX_HOME_BASE:-/home/www-data/.codex/providers}"

mkdir -p "${CODEX_HOME_BASE_PATH}"

php artisan ai-providers:sync-stats --provider=chatgpt_codex >/dev/null 2>&1 || true
