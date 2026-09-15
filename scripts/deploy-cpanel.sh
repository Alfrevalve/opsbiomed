#!/usr/bin/env bash

set -Eeuo pipefail

PHP_BIN="${CPANEL_PHP_BIN:-php}"
COMPOSER_BIN="${CPANEL_COMPOSER_BIN:-composer}"
APP_PATH="${CPANEL_APP_PATH:-$(pwd)}"

cd "$APP_PATH"

BACKUP_DIR="${BACKUP_DIR:-storage/backups/$(date +%Y%m%d-%H%M%S)}"
mkdir -p "$BACKUP_DIR"

if [[ -f .env ]]; then
    cp .env "$BACKUP_DIR/.env"
fi

if [[ -d storage/app/private ]]; then
    tar -czf "$BACKUP_DIR/private-storage.tgz" -C storage/app/private .
fi

maintenance_enabled=0

cleanup() {
    if [[ "$maintenance_enabled" == "1" ]]; then
        "$PHP_BIN" artisan up || true
    fi
}

trap cleanup EXIT

"$PHP_BIN" artisan down --render=errors::503 --retry=60
maintenance_enabled=1

"$COMPOSER_BIN" install --no-dev --no-interaction --prefer-dist --optimize-autoloader
"$PHP_BIN" artisan migrate --force

if [[ ! -e public/storage && ! -L public/storage ]]; then
    "$PHP_BIN" artisan storage:link
fi

"$PHP_BIN" artisan optimize:clear
"$PHP_BIN" artisan config:cache
"$PHP_BIN" artisan route:cache
"$PHP_BIN" artisan view:cache
"$PHP_BIN" artisan event:cache
"$PHP_BIN" artisan up
maintenance_enabled=0

echo "OPS BIOMED deployment completed. Backup: $BACKUP_DIR"
