#!/usr/bin/env bash
set -euo pipefail

if ! command -v php >/dev/null 2>&1; then
  echo "PHP no esta instalado. Instala PHP 8.3+ antes de continuar." >&2
  exit 1
fi

if ! command -v composer >/dev/null 2>&1; then
  echo "Composer no esta instalado. Instala Composer antes de continuar." >&2
  exit 1
fi

composer install

if [ ! -f .env ]; then
  cp .env.example .env
fi

mkdir -p database storage/app/private
touch database/database.sqlite

php artisan key:generate --ansi
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider" --force
php artisan migrate
php artisan db:seed --class=RoleAndPermissionSeeder
if [[ -n "${INITIAL_ADMIN_PASSWORD:-}" ]]; then
  php artisan db:seed --class=InitialUserSeeder
fi

if command -v npm >/dev/null 2>&1; then
  npm install
  npm run build
fi

php artisan test
