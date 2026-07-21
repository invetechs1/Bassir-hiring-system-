#!/usr/bin/env bash
set -Eeuo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

[[ -f artisan ]] || { echo "[FAIL] artisan not found"; exit 1; }
[[ -f .env ]] || { echo "[FAIL] .env not found"; exit 1; }
command -v php >/dev/null 2>&1 || { echo "[FAIL] php command is required"; exit 1; }

LOG_DIR="$ROOT_DIR/storage/logs"
mkdir -p "$LOG_DIR"
TS="$(date +%Y%m%d-%H%M%S)"
LOG_FILE="$LOG_DIR/qa-server-suite-$TS.log"
exec > >(tee -a "$LOG_FILE") 2>&1

echo "Bassir QA server suite: $TS"
echo "Project root: $ROOT_DIR"
echo "----------------------------------------"

echo "==> PHP syntax lint"
find app bootstrap config database routes scripts -name '*.php' -print0 | xargs -0 -n 1 php -l >/tmp/bassir-php-lint.log
cat /tmp/bassir-php-lint.log
rm -f /tmp/bassir-php-lint.log
echo "[OK] PHP syntax lint"

echo "==> Composer autoload presence"
test -f vendor/autoload.php
echo "[OK] vendor/autoload.php exists"

echo "==> Laravel cache commands"
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
echo "[OK] Laravel cache commands"

echo "==> Database migration check"
if [[ "${QA_FRESH_DATABASE:-false}" == "true" ]]; then
  echo "[WARN] Running migrate:fresh --seed because QA_FRESH_DATABASE=true"
  php artisan migrate:fresh --seed --force
else
  php artisan migrate --force
  php artisan migrate:status
fi
echo "[OK] Database migration check"

echo "==> Preflight"
php scripts/preflight.php
echo "[OK] Preflight"

echo "==> Smoke test"
bash scripts/smoke-test-suite.sh
echo "[OK] Smoke test"

echo "----------------------------------------"
echo "QA server suite completed successfully"
echo "Log: $LOG_FILE"
