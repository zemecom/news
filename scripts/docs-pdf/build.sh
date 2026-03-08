#!/bin/sh
set -eu

ROOT_DIR="$(CDPATH= cd -- "$(dirname "$0")/../.." && pwd)"
OUT_DIR="${ROOT_DIR}/artifacts/docs"
ASSETS_DIR="${ROOT_DIR}/scripts/docs-pdf"

mkdir -p "${OUT_DIR}"

COMMON_ARGS="
  --standalone
  --toc
  --toc-depth=3
  --from=gfm+raw_html
  --pdf-engine=prince
  --css=${ASSETS_DIR}/pandoc-pdf.css
  --lua-filter=${ASSETS_DIR}/strip-md-links.lua
  --lua-filter=${ASSETS_DIR}/mermaid-panel.lua
  --resource-path=${ROOT_DIR}:${ROOT_DIR}/docs:${ROOT_DIR}/docs/start:${ROOT_DIR}/docs/architecture:${ROOT_DIR}/docs/guides:${ROOT_DIR}/docs/reference:${ROOT_DIR}/docs/interview:${ASSETS_DIR}
"

build_pdf() {
    output="$1"
    shift

    # shellcheck disable=SC2086
    pandoc ${COMMON_ARGS} -o "${output}" "$@"
}

build_pdf \
    "${OUT_DIR}/smartnews-onboarding-learning.pdf" \
    "${ASSETS_DIR}/front-onboarding.md" \
    "${ROOT_DIR}/README.md" \
    "${ROOT_DIR}/docs/start/junior-onboarding.md" \
    "${ROOT_DIR}/docs/reference/api/news-api.md" \
    "${ROOT_DIR}/docs/reference/config/env.md" \
    "${ROOT_DIR}/docs/guides/runtime-operations.md" \
    "${ROOT_DIR}/docs/start/learning-path.md"

build_pdf \
    "${OUT_DIR}/smartnews-architecture-runtime.pdf" \
    "${ASSETS_DIR}/front-architecture.md" \
    "${ROOT_DIR}/docs/architecture/00-overview.md" \
    "${ROOT_DIR}/docs/architecture/01-entrypoint.md" \
    "${ROOT_DIR}/docs/architecture/02-app.md" \
    "${ROOT_DIR}/docs/architecture/03-delivery.md" \
    "${ROOT_DIR}/docs/architecture/04-crawler.md" \
    "${ROOT_DIR}/docs/architecture/05-intelligence.md" \
    "${ROOT_DIR}/docs/architecture/06-catalog-shared.md" \
    "${ROOT_DIR}/docs/architecture/07-admin-and-debugging.md" \
    "${ROOT_DIR}/docs/architecture/08-infrastructure.md" \
    "${ROOT_DIR}/docs/guides/adding-a-feature.md" \
    "${ROOT_DIR}/docs/architecture/10-end-to-end-flows.md" \
    "${ROOT_DIR}/docs/architecture/11-data-model-and-indexes.md" \
    "${ROOT_DIR}/docs/architecture/12-events-queues-and-messaging.md" \
    "${ROOT_DIR}/docs/architecture/13-security-and-failure-modes.md" \
    "${ROOT_DIR}/docs/architecture/14-testing-and-quality-gates.md" \
    "${ROOT_DIR}/docs/architecture/17-known-limitations-and-tradeoffs.md"

build_pdf \
    "${OUT_DIR}/smartnews-reference.pdf" \
    "${ASSETS_DIR}/front-reference.md" \
    "${ROOT_DIR}/docs/interview/question-bank.md" \
    "${ROOT_DIR}/docs/interview/glossary-and-cheatsheet.md" \
    "${ROOT_DIR}/docs/PROJECT_MEMORY.md" \
    "${ROOT_DIR}/docs/PROJECT_STRUCTURE.md" \
    "${ROOT_DIR}/docs/PROJECT_INTERFACE.md"

printf 'Built PDFs in %s\n' "${OUT_DIR}"
