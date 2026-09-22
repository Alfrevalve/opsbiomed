#!/usr/bin/env bash

set -Eeuo pipefail

MODE="${1:---dry-run}"
TARGET_ENV="${CPANEL_TARGET_ENV:-}"
APP_PATH="${CPANEL_APP_PATH:-}"
EXPECTED_PATH="${EXPECTED_CPANEL_APP_PATH:-}"

fail() {
    printf 'Deployment refused: %s\n' "$1" >&2
    exit 1
}

if [[ "$MODE" != "--dry-run" && "$MODE" != "--execute-staging" ]]; then
    fail 'use --dry-run or the explicitly gated --execute-staging mode.'
fi

[[ "$TARGET_ENV" == "staging" ]] || fail 'only CPANEL_TARGET_ENV=staging is accepted.'
[[ -n "$APP_PATH" && -n "$EXPECTED_PATH" ]] || fail 'CPANEL_APP_PATH and EXPECTED_CPANEL_APP_PATH must both be configured.'
[[ "$APP_PATH" == "$EXPECTED_PATH" ]] || fail 'the configured path does not exactly match the expected path.'
[[ "$APP_PATH" == /* ]] || fail 'the application path must be absolute.'
[[ "$APP_PATH" != *'/public_html'* ]] || fail 'the Laravel application must be outside the public document root.'

if [[ "$MODE" == "--dry-run" ]]; then
    printf 'Dry run only. Target: staging. Validated configured path: %s\n' "$APP_PATH"
    printf 'No files, database, release pointer, or remote service were changed.\n'
    exit 0
fi

[[ -d "$APP_PATH" ]] || fail 'the configured staging application directory does not exist.'
APP_PATH_REAL="$(realpath -e -- "$APP_PATH")"
EXPECTED_PATH_REAL="$(realpath -e -- "$EXPECTED_PATH")"
[[ "$APP_PATH_REAL" == "$EXPECTED_PATH_REAL" ]] || fail 'canonical application paths do not match.'
[[ -n "${RELEASE_ID:-}" && "$RELEASE_ID" =~ ^[a-fA-F0-9]{7,64}$ ]] || fail 'RELEASE_ID must be a commit SHA.'
[[ -n "${DEPLOY_PACKAGE:-}" && -f "$DEPLOY_PACKAGE" ]] || fail 'DEPLOY_PACKAGE must point to a built release archive.'
[[ -n "${DEPLOY_PACKAGE_SHA256:-}" && "$DEPLOY_PACKAGE_SHA256" =~ ^[a-fA-F0-9]{64}$ ]] || fail 'DEPLOY_PACKAGE_SHA256 must be a SHA-256 digest.'
[[ -n "${CPANEL_PHP_BIN:-}" && -x "$CPANEL_PHP_BIN" ]] || fail 'configure an executable CPANEL_PHP_BIN.'
[[ -n "${CPANEL_COMPOSER_BIN:-}" && -x "$CPANEL_COMPOSER_BIN" ]] || fail 'configure an executable CPANEL_COMPOSER_BIN.'
[[ -n "${DEPLOY_URL:-}" && "$DEPLOY_URL" == https://* ]] || fail 'DEPLOY_URL must use HTTPS.'
[[ "${PRE_DEPLOY_BACKUP_VERIFIED:-}" == "1" && -n "${PRE_DEPLOY_BACKUP_MANIFEST:-}" && -s "$PRE_DEPLOY_BACKUP_MANIFEST" ]] || fail 'a reviewed, non-empty pre-deployment backup manifest is required.'
[[ -n "${CPANEL_BACKUP_ROOT:-}" && "$CPANEL_BACKUP_ROOT" == "${EXPECTED_CPANEL_BACKUP_ROOT:-}" ]] || fail 'the configured backup path does not exactly match its expected path.'
[[ -n "${CPANEL_DB_NAME:-}" && "$CPANEL_DB_NAME" == *staging* ]] || fail 'CPANEL_DB_NAME must identify a staging database.'
[[ -n "${CPANEL_DB_HOST:-}" && "$CPANEL_DB_HOST" == "${EXPECTED_CPANEL_DB_HOST:-}" ]] || fail 'database host does not exactly match the configured staging host.'
[[ -n "${DEPLOY_URL:-}" && "$DEPLOY_URL" == "${EXPECTED_STAGING_URL:-}" ]] || fail 'deployment URL does not exactly match the configured staging URL.'
[[ -L "$APP_PATH/current" ]] || fail 'a previous active release is required for a reversible deployment.'

PHP_VERSION="$("$CPANEL_PHP_BIN" -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')"
[[ "$PHP_VERSION" == "8.3" ]] || fail 'the configured CLI PHP must be version 8.3.'
command -v flock >/dev/null || fail 'flock is required to serialize deployments.'
command -v realpath >/dev/null || fail 'realpath is required to validate canonical paths.'
command -v curl >/dev/null || fail 'curl is required for the post-activation health check.'
command -v ln >/dev/null || fail 'ln is required to create release symlinks.'
command -v mv >/dev/null && mv --help 2>&1 | grep -q -- '-T' || fail 'atomic symlink replacement requires mv -T.'

BASE="$APP_PATH_REAL"
RELEASES="$BASE/releases"
SHARED="$BASE/shared"
RELEASE="$RELEASES/$RELEASE_ID"
CURRENT="$BASE/current"
PACKAGE_REAL="$(realpath -e -- "$DEPLOY_PACKAGE")"
PACKAGE_ACTUAL_SHA256="$(sha256sum "$PACKAGE_REAL" | cut -d ' ' -f 1)"
[[ "$PACKAGE_ACTUAL_SHA256" == "$DEPLOY_PACKAGE_SHA256" ]] || fail 'release archive checksum does not match.'
BACKUP_ROOT_REAL="$(realpath -e -- "$CPANEL_BACKUP_ROOT")"
BACKUP_MANIFEST_REAL="$(realpath -e -- "$PRE_DEPLOY_BACKUP_MANIFEST")"
BACKUP_DIR="$(dirname -- "$BACKUP_MANIFEST_REAL")"
[[ "$BACKUP_MANIFEST_REAL" == "$BACKUP_ROOT_REAL"/*/backup-manifest.txt ]] || fail 'backup manifest is outside the configured backup directory.'
grep -Fxq 'target=staging' "$BACKUP_MANIFEST_REAL" || fail 'backup manifest is not for staging.'
grep -Fxq 'encrypted=true' "$BACKUP_MANIFEST_REAL" || fail 'backup manifest does not confirm encryption.'
grep -Fxq "database=$CPANEL_DB_NAME" "$BACKUP_MANIFEST_REAL" || fail 'backup manifest database does not match staging.'
[[ -f "$BACKUP_DIR/SHA256SUMS" ]] || fail 'backup checksums are missing.'
(cd "$BACKUP_DIR" && sha256sum --check --status SHA256SUMS) || fail 'backup checksum verification failed.'

LOCK_FILE="$BASE/.deploy.lock"
[[ ! -L "$LOCK_FILE" ]] || fail 'deployment lock file cannot be a symlink.'
exec 9>"$LOCK_FILE"
flock -n 9 || fail 'another deployment is in progress.'

[[ ! -L "$RELEASES" ]] || fail 'releases cannot be a symlink.'
mkdir -p -- "$RELEASES"
[[ "$(realpath -e -- "$RELEASES")" == "$BASE/releases" ]] || fail 'releases must be a real directory inside the configured application path.'
[[ ! -L "$SHARED" && "$(realpath -e -- "$SHARED")" == "$BASE/shared" ]] || fail 'shared must be a real directory inside the configured application path.'
[[ ! -e "$RELEASE" && ! -L "$RELEASE" ]] || fail 'the release ID already exists; releases are immutable.'
[[ -f "$SHARED/.env" && -d "$SHARED/storage" ]] || fail 'shared .env and storage must be provisioned before deployment.'
[[ ! -e "$CURRENT" || -L "$CURRENT" ]] || fail 'current exists but is not a symlink; refusing to replace it.'
PREVIOUS_RELEASE="$(realpath -e -- "$CURRENT")"
[[ "$PREVIOUS_RELEASE" == "$RELEASES"/* ]] || fail 'current points outside the configured releases directory.'

CAPABILITY_DIR="$RELEASES/.capability-$RELEASE_ID"
CAPABILITY_LINK="$RELEASES/.capability-link-$RELEASE_ID"
CAPABILITY_MOVED="$RELEASES/.capability-moved-$RELEASE_ID"
[[ ! -e "$CAPABILITY_DIR" && ! -e "$CAPABILITY_LINK" && ! -e "$CAPABILITY_MOVED" ]] || fail 'symlink capability check paths already exist.'
mkdir -- "$CAPABILITY_DIR"
ln -s "$CAPABILITY_DIR" "$CAPABILITY_LINK"
mv -Tf -- "$CAPABILITY_LINK" "$CAPABILITY_MOVED"
[[ -L "$CAPABILITY_MOVED" ]] || fail 'symlink/atomic rename capability check failed.'
rm -- "$CAPABILITY_MOVED"
rmdir -- "$CAPABILITY_DIR"

mkdir -- "$RELEASE"
tar -xzf "$PACKAGE_REAL" --no-same-owner -C "$RELEASE"
mkdir -p "$RELEASE/bootstrap/cache" "$SHARED/storage/framework/sessions" "$SHARED/storage/framework/views" "$SHARED/storage/framework/cache/data" "$SHARED/storage/logs" "$SHARED/storage/app/private"
chmod ug+rwX "$RELEASE/bootstrap/cache" "$SHARED/storage" "$SHARED/storage/framework" "$SHARED/storage/framework/sessions" "$SHARED/storage/framework/views" "$SHARED/storage/framework/cache" "$SHARED/storage/framework/cache/data" "$SHARED/storage/logs" "$SHARED/storage/app" "$SHARED/storage/app/private"
ln -s "$SHARED/.env" "$RELEASE/.env"
ln -s "$SHARED/storage" "$RELEASE/storage"

"$CPANEL_COMPOSER_BIN" install --working-dir="$RELEASE" --no-dev --no-interaction --prefer-dist --optimize-autoloader
[[ -f "$RELEASE/public/build/manifest.json" ]] || fail 'the artifact does not contain the compiled frontend assets.'

cd "$RELEASE"
CPANEL_DB_NAME="$CPANEL_DB_NAME" CPANEL_DB_HOST="$CPANEL_DB_HOST" DEPLOY_URL="$DEPLOY_URL" "$CPANEL_PHP_BIN" -r '
    require "vendor/autoload.php";
    $app = require "bootstrap/app.php";
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    $valid = config("app.env") === "staging"
        && config("app.url") === getenv("DEPLOY_URL")
        && config("database.default") === "mysql"
        && config("database.connections.mysql.database") === getenv("CPANEL_DB_NAME")
        && config("database.connections.mysql.host") === getenv("CPANEL_DB_HOST")
        && blank(config("database.connections.mysql.url"));
    exit($valid ? 0 : 1);
' || fail 'release .env does not match the approved staging application and database.'
"$CPANEL_PHP_BIN" artisan about --only=environment --no-ansi >/dev/null
"$CPANEL_PHP_BIN" artisan migrate --force --no-ansi
"$CPANEL_PHP_BIN" artisan config:cache --no-ansi
"$CPANEL_PHP_BIN" artisan route:cache --no-ansi
"$CPANEL_PHP_BIN" artisan view:cache --no-ansi
"$CPANEL_PHP_BIN" artisan event:cache --no-ansi

NEXT_LINK="$BASE/current.next.$RELEASE_ID"
[[ ! -e "$NEXT_LINK" && ! -L "$NEXT_LINK" ]] || fail 'temporary activation link already exists.'
ln -s "$RELEASE" "$NEXT_LINK"
mv -Tf -- "$NEXT_LINK" "$CURRENT"

if ! curl --fail --silent --show-error --max-time 20 "$DEPLOY_URL/up" >/dev/null; then
    ROLLBACK_LINK="$BASE/current.rollback.$RELEASE_ID"
    ln -s "$PREVIOUS_RELEASE" "$ROLLBACK_LINK"
    mv -Tf -- "$ROLLBACK_LINK" "$CURRENT"
    fail 'health check failed; code pointer was restored to the previous release.'
fi

printf 'Staging release %s is active. Previous release: %s\n' "$RELEASE_ID" "${PREVIOUS_RELEASE:-none}"
printf 'No releases were deleted. Review retention and remove old releases only with a separately approved procedure.\n'
