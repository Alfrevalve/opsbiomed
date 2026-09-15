#!/usr/bin/env bash

set -Eeuo pipefail

BASE_URL="${1:-${APP_URL:-}}"

if [[ -z "$BASE_URL" ]]; then
    echo "Usage: $0 https://opsbiomed.alfreval.com" >&2
    exit 1
fi

BASE_URL="${BASE_URL%/}"

curl --fail --silent --show-error --location --max-time 20 "$BASE_URL/up" >/dev/null
echo "Health endpoint OK: $BASE_URL/up"

if command -v php >/dev/null 2>&1 && [[ -f artisan ]]; then
    php artisan migrate:status >/dev/null
    php artisan schedule:list >/dev/null
    echo "Artisan migration and scheduler checks OK."
fi
