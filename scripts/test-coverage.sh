#!/usr/bin/env sh

set -eu

ROOT_DIR=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
MIN_FILE="$ROOT_DIR/.coverage-min"
ARTIFACT_DIR="$ROOT_DIR/artifacts/coverage"

MINIMUM=0
if [ -f "$MIN_FILE" ]; then
    MINIMUM=$(tr -d '[:space:]' < "$MIN_FILE")
fi

if [ -z "$MINIMUM" ]; then
    MINIMUM=0
fi

case "$MINIMUM" in
    *[!0-9]*)
        echo "Invalid coverage minimum in $MIN_FILE: $MINIMUM" >&2
        exit 1
        ;;
esac

mkdir -p "$ARTIFACT_DIR"

echo "Running coverage with minimum ${MINIMUM}%"

exec php \
    -d pcov.enabled=1 \
    -d pcov.directory="$ROOT_DIR" \
    ./vendor/bin/pest \
    tests/Unit \
    tests/Feature \
    --exclude-group=acceptance \
    --coverage \
    --coverage-text=php://stdout \
    --only-summary-for-coverage-text \
    --coverage-clover="$ARTIFACT_DIR/clover.xml" \
    --coverage-html="$ARTIFACT_DIR/html" \
    --min="$MINIMUM"
