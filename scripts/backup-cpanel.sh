#!/usr/bin/env bash

set -Eeuo pipefail
umask 077

MODE="${1:---dry-run}"
TARGET_ENV="${CPANEL_TARGET_ENV:-}"
APP_PATH="${CPANEL_APP_PATH:-}"
EXPECTED_APP_PATH="${EXPECTED_CPANEL_APP_PATH:-}"
DB_NAME="${CPANEL_DB_NAME:-}"
EXPECTED_DB_NAME="${EXPECTED_CPANEL_DB_NAME:-}"
DB_HOST="${CPANEL_DB_HOST:-}"
EXPECTED_DB_HOST="${EXPECTED_CPANEL_DB_HOST:-}"
BACKUP_ROOT="${CPANEL_BACKUP_ROOT:-}"
EXPECTED_BACKUP_ROOT="${EXPECTED_CPANEL_BACKUP_ROOT:-}"
BACKUP_OPERATOR="${BACKUP_OPERATOR:-unknown}"

fail() {
    printf 'Backup refused: %s\n' "$1" >&2
    exit 1
}

if [[ "$MODE" != "--dry-run" && "$MODE" != "--execute-staging" ]]; then
    fail 'use --dry-run or the explicitly gated --execute-staging mode.'
fi

[[ "$TARGET_ENV" == "staging" ]] || fail 'only CPANEL_TARGET_ENV=staging is accepted.'
[[ -n "$APP_PATH" && "$APP_PATH" == "$EXPECTED_APP_PATH" ]] || fail 'application path does not exactly match the configured expected path.'
[[ -n "$DB_NAME" && "$DB_NAME" == "$EXPECTED_DB_NAME" && "$DB_NAME" == *staging* ]] || fail 'database must exactly match its configured staging name.'
[[ -n "$DB_HOST" && "$DB_HOST" == "$EXPECTED_DB_HOST" ]] || fail 'database host must exactly match its configured staging host.'
[[ -n "$BACKUP_ROOT" && "$BACKUP_ROOT" == "$EXPECTED_BACKUP_ROOT" ]] || fail 'backup path does not exactly match the configured expected path.'
[[ "$APP_PATH" == /* && "$BACKUP_ROOT" == /* ]] || fail 'application and backup paths must be absolute.'
[[ "$APP_PATH" != *'/public_html'* && "$BACKUP_ROOT" != *'/public_html'* ]] || fail 'application and backups must remain outside public_html.'

if [[ "$MODE" == "--dry-run" ]]; then
    printf 'Dry run only. Target: staging. Database: %s. Backup destination: %s\n' "$DB_NAME" "$BACKUP_ROOT"
    printf 'No database, files, or remote service were changed.\n'
    exit 0
fi

[[ -d "$APP_PATH" ]] || fail 'the configured staging application directory does not exist.'
APP_PATH_REAL="$(realpath -e -- "$APP_PATH")"
BACKUP_ROOT_REAL="$(realpath -m -- "$BACKUP_ROOT")"
[[ "$BACKUP_ROOT_REAL" != "$APP_PATH_REAL" && "$BACKUP_ROOT_REAL" != "$APP_PATH_REAL"/* ]] || fail 'backup destination must be outside the application tree.'
[[ "$BACKUP_ROOT_REAL" != *'/public_html'* ]] || fail 'canonical backup destination must remain outside public_html.'
[[ -n "${MYSQL_CNF:-}" && -f "$MYSQL_CNF" ]] || fail 'MYSQL_CNF must point to a MySQL client file outside the repository.'
MYSQL_CNF_REAL="$(realpath -e -- "$MYSQL_CNF")"
[[ "$MYSQL_CNF_REAL" != "$APP_PATH_REAL"/* ]] || fail 'MySQL credentials must be outside the application tree.'
[[ "$MYSQL_CNF_REAL" != *'/public_html'* ]] || fail 'MySQL credentials must remain outside public_html.'
[[ "$(stat -c '%a' "$MYSQL_CNF_REAL")" == "600" ]] || fail 'MYSQL_CNF must have permissions 0600.'
[[ -n "${BACKUP_GPG_RECIPIENT:-}" ]] || fail 'configure the approved public-key recipient for backup encryption.'
[[ -f "$APP_PATH_REAL/shared/.env" && -d "$APP_PATH_REAL/shared/storage/app/private" ]] || fail 'shared .env and private evidence storage are required.'

MYSQLDUMP_BIN="${MYSQLDUMP_BIN:-mysqldump}"
GPG_BIN="${GPG_BIN:-gpg}"
for executable in "$MYSQLDUMP_BIN" "$GPG_BIN" tar gzip sha256sum realpath; do
    command -v "$executable" >/dev/null || fail "required backup utility is unavailable: $executable."
done

mkdir -p -- "$BACKUP_ROOT_REAL"
chmod 700 -- "$BACKUP_ROOT_REAL"
BACKUP_ID="$(date -u +%Y%m%dT%H%M%SZ)"
BACKUP_DIR="$BACKUP_ROOT_REAL/$BACKUP_ID"
TEMP_DIR="$BACKUP_ROOT_REAL/.tmp-$BACKUP_ID-$$"
[[ ! -e "$BACKUP_DIR" && ! -e "$TEMP_DIR" ]] || fail 'backup destination already exists.'
mkdir -m 700 -- "$TEMP_DIR"
cleanup() {
    if [[ -d "$TEMP_DIR" && "$(realpath -e -- "$TEMP_DIR")" == "$BACKUP_ROOT_REAL"/* ]]; then
        rm -rf -- "$TEMP_DIR"
    fi
}
trap cleanup EXIT

"$MYSQLDUMP_BIN" --defaults-extra-file="$MYSQL_CNF_REAL" --host="$DB_HOST" --single-transaction --routines --triggers --databases "$DB_NAME" \
    | gzip -c \
    | "$GPG_BIN" --batch --yes --trust-model always --encrypt --recipient "$BACKUP_GPG_RECIPIENT" --output "$TEMP_DIR/database.sql.gz.gpg"

tar -czf - -C "$APP_PATH_REAL" shared/.env shared/storage/app/private \
    | "$GPG_BIN" --batch --yes --trust-model always --encrypt --recipient "$BACKUP_GPG_RECIPIENT" --output "$TEMP_DIR/private-evidence-and-env.tar.gz.gpg"

[[ -s "$TEMP_DIR/database.sql.gz.gpg" && -s "$TEMP_DIR/private-evidence-and-env.tar.gz.gpg" ]] || fail 'one or more encrypted backups are empty.'
(cd "$TEMP_DIR" && sha256sum ./*.gpg > SHA256SUMS)
{
    printf 'backup_id=%s\n' "$BACKUP_ID"
    printf 'target=staging\n'
    printf 'database=%s\n' "$DB_NAME"
    printf 'encrypted=true\n'
    printf 'created_utc=%s\n' "$(date -u +%FT%TZ)"
    printf 'operator=%s\n' "$BACKUP_OPERATOR"
    printf 'release_id=%s\n' "${RELEASE_ID:-unknown}"
    printf 'database_backup_bytes=%s\n' "$(stat -c '%s' "$TEMP_DIR/database.sql.gz.gpg")"
    printf 'private_backup_bytes=%s\n' "$(stat -c '%s' "$TEMP_DIR/private-evidence-and-env.tar.gz.gpg")"
    printf 'sha256_manifest=SHA256SUMS\n'
    printf 'restore_verified=false\n'
} > "$TEMP_DIR/backup-manifest.txt"
mv -- "$TEMP_DIR" "$BACKUP_DIR"
printf 'Encrypted staging backup created: id=%s database=%s directory=%s\n' "$BACKUP_ID" "$DB_NAME" "$BACKUP_DIR"
printf 'Restore is not considered verified until the encrypted files are restored and checked in isolated staging.\n'
