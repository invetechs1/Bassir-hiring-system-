#!/usr/bin/env bash
set -Eeuo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

[[ -f .env ]] || { echo "[FAIL] .env not found"; exit 1; }

need_cmd() {
  command -v "$1" >/dev/null 2>&1 || { echo "[FAIL] Missing required command: $1"; exit 1; }
}

has_cmd() {
  command -v "$1" >/dev/null 2>&1
}

env_value() {
  local key="$1"
  local line
  line="$(grep -E "^${key}=" .env 2>/dev/null | tail -n 1 || true)"
  line="${line#*=}"
  line="${line%\"}"
  line="${line#\"}"
  line="${line%\'}"
  line="${line#\'}"
  printf '%s' "$line"
}

need_cmd tar
need_cmd gzip
need_cmd find
if has_cmd sha256sum; then
  CHECKSUM_MODE="sha256sum"
elif has_cmd shasum; then
  CHECKSUM_MODE="shasum"
else
  echo "[FAIL] Missing required checksum command: sha256sum or shasum"
  exit 1
fi

BACKUP_ROOT="${BACKUP_ROOT:-$ROOT_DIR/storage/backups}"
RETENTION_DAYS="${RETENTION_DAYS:-14}"
TIMESTAMP="$(date +%Y%m%d-%H%M%S)"
RUN_DIR="$BACKUP_ROOT/$TIMESTAMP"
LOG_DIR="$ROOT_DIR/storage/logs"
LOG_FILE="$LOG_DIR/ops-backup-$TIMESTAMP.log"
mkdir -p "$RUN_DIR" "$LOG_DIR"
exec > >(tee -a "$LOG_FILE") 2>&1

echo "Backup run: $TIMESTAMP"
echo "Backup root: $BACKUP_ROOT"

DB_HOST="$(env_value DB_HOST)"
DB_PORT="$(env_value DB_PORT)"
DB_DATABASE="$(env_value DB_DATABASE)"
DB_USERNAME="$(env_value DB_USERNAME)"
DB_PASSWORD="$(env_value DB_PASSWORD)"

if [[ -n "$DB_HOST" && -n "$DB_DATABASE" && -n "$DB_USERNAME" ]] && command -v mysqldump >/dev/null 2>&1; then
  DB_DUMP_FILE="$RUN_DIR/database.sql.gz"
  echo "[INFO] Creating MySQL dump..."
  MYSQL_PWD="$DB_PASSWORD" mysqldump \
    --single-transaction \
    --quick \
    --skip-lock-tables \
    --host="$DB_HOST" \
    --port="${DB_PORT:-3306}" \
    --user="$DB_USERNAME" \
    "$DB_DATABASE" | gzip -9 > "$DB_DUMP_FILE"
  echo "[OK] Database backup: $DB_DUMP_FILE"
else
  echo "[WARN] MySQL backup skipped (mysqldump missing or DB env incomplete)"
fi

PRIVATE_STORAGE="$ROOT_DIR/storage/app/private"
if [[ -d "$PRIVATE_STORAGE" ]]; then
  FILES_DUMP_FILE="$RUN_DIR/private-storage.tar.gz"
  echo "[INFO] Archiving private storage..."
  tar -czf "$FILES_DUMP_FILE" -C "$ROOT_DIR/storage/app" private
  echo "[OK] Private storage backup: $FILES_DUMP_FILE"
else
  echo "[WARN] Private storage path missing: $PRIVATE_STORAGE"
fi

MANIFEST_FILE="$RUN_DIR/manifest.txt"
{
  echo "Backup timestamp: $TIMESTAMP"
  echo "Project root: $ROOT_DIR"
  echo "Retention days: $RETENTION_DAYS"
  echo "Artifacts:"
  find "$RUN_DIR" -maxdepth 1 -type f ! -name manifest.txt -print | sed "s#^# - #"
} > "$MANIFEST_FILE"

if [[ "$CHECKSUM_MODE" == "sha256sum" ]]; then
  find "$RUN_DIR" -maxdepth 1 -type f ! -name manifest.txt -print0 | xargs -0 sha256sum >> "$MANIFEST_FILE" || true
else
  find "$RUN_DIR" -maxdepth 1 -type f ! -name manifest.txt -print0 | xargs -0 shasum -a 256 >> "$MANIFEST_FILE" || true
fi

echo "[OK] Manifest created: $MANIFEST_FILE"

echo "[INFO] Cleaning backups older than $RETENTION_DAYS days..."
find "$BACKUP_ROOT" -mindepth 1 -maxdepth 1 -type d -mtime +"$RETENTION_DAYS" -exec rm -rf {} +
echo "[OK] Retention cleanup complete"

echo "Backup completed successfully"
echo "Log: $LOG_FILE"
