#!/usr/bin/env sh
set -eu

BASE_URL="${1:-http://localhost:8080}"

NEWS_JSON="$(curl -fsS "${BASE_URL}/api/news?per_page=1")"
NEWS_ID="$(printf '%s' "$NEWS_JSON" | php -r '$d=json_decode(stream_get_contents(STDIN), true); echo $d["data"][0]["id"] ?? "";')"

if [ -z "$NEWS_ID" ]; then
  echo "smoke-api failed: no news id in /api/news response" >&2
  exit 1
fi

SHOW_ID="$(curl -fsS "${BASE_URL}/api/news/${NEWS_ID}" | php -r '$d=json_decode(stream_get_contents(STDIN), true); echo $d["data"]["id"] ?? "";')"

if [ "$SHOW_ID" != "$NEWS_ID" ]; then
  echo "smoke-api failed: /api/news/{id} returned unexpected id" >&2
  exit 1
fi

echo "smoke-api ok: ${NEWS_ID}"
