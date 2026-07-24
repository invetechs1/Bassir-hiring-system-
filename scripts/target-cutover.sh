#!/usr/bin/env bash
set -Eeuo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

LOG_DIR="$ROOT_DIR/storage/logs"
mkdir -p "$LOG_DIR"
RUN_TS="$(date +%Y%m%d-%H%M%S)"
LOG_FILE="$LOG_DIR/target-cutover-${RUN_TS}.log"

exec > >(tee -a "$LOG_FILE") 2>&1

fail() {
    echo "[FAIL] $*"
    exit 1
}

ok() {
    echo "[OK] $*"
}

run_step() {
    local label="$1"
    shift
    echo
    echo "==> ${label}"
    "$@" || fail "${label} failed"
    ok "${label}"
}

need_cmd() {
    command -v "$1" >/dev/null 2>&1 || fail "Missing required command: $1"
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

echo "Bassir target cutover started at ${RUN_TS}"
echo "Project root: ${ROOT_DIR}"
echo "Log file: ${LOG_FILE}"

[[ -f artisan ]] || fail "artisan not found. Run this script from project root."
[[ -f .env ]] || fail ".env not found. Create it first from .env.example."

need_cmd php
need_cmd composer
need_cmd curl

php -r 'exit(version_compare(PHP_VERSION, "8.2.0", ">=") ? 0 : 1);' \
    || fail "PHP 8.2+ is required"
ok "PHP version is compatible"

RUN_SEED="${RUN_SEED:-false}"                    # true for first deploy only
SKIP_COMPOSER_INSTALL="${SKIP_COMPOSER_INSTALL:-false}"
APP_URL_OVERRIDE="${APP_URL_OVERRIDE:-}"

if [[ "$SKIP_COMPOSER_INSTALL" != "true" ]]; then
    run_step "Composer install" composer install --no-dev --optimize-autoloader --no-interaction
else
    echo "[INFO] Skipping composer install (SKIP_COMPOSER_INSTALL=true)"
fi

run_step "Laravel optimize clear" php artisan optimize:clear
run_step "Database migrate" php artisan migrate --force

if [[ "$RUN_SEED" == "true" ]]; then
    run_step "Database seed" php artisan db:seed --force
else
    echo "[INFO] Skipping seed (RUN_SEED=false)"
fi

run_step "Cache config" php artisan config:cache
run_step "Cache routes" php artisan route:cache
run_step "Cache views" php artisan view:cache

run_step "Preflight" php scripts/preflight.php

APP_URL="${APP_URL_OVERRIDE:-$(env_value APP_URL)}"
[[ -n "$APP_URL" ]] || fail "APP_URL is empty. Set APP_URL in .env or pass APP_URL_OVERRIDE."
APP_URL="${APP_URL%/}"

echo
echo "==> HTTP smoke checks (${APP_URL})"

HEALTH_BODY="$(curl -fsS --max-time 20 "${APP_URL}/health")" || fail "Health endpoint check failed"
echo "Health response: ${HEALTH_BODY}"
echo "$HEALTH_BODY" | grep -q '"database":"ok"' || fail "Health check database status is not ok"
echo "$HEALTH_BODY" | grep -q '"storage":"ok"' || fail "Health check storage status is not ok"
ok "Health endpoint is healthy"

HEADERS_FILE="$(mktemp)"
curl -fsS -D "$HEADERS_FILE" -o /dev/null --max-time 20 "${APP_URL}/" || fail "Homepage request failed"

grep -qi '^content-security-policy:' "$HEADERS_FILE" || fail "Missing Content-Security-Policy header"
grep -qi '^x-trace-id:' "$HEADERS_FILE" || fail "Missing X-Trace-Id header"
grep -qi '^cache-control:.*no-store' "$HEADERS_FILE" || fail "Missing no-store cache policy on login page"
ok "Security/observability headers are present"

rm -f "$HEADERS_FILE"

MOBILE_LOGIN_CODE="$(curl -sS -o /dev/null -w '%{http_code}' --max-time 20 \
  -X POST "${APP_URL}/api/mobile/auth/login" \
  -H 'Content-Type: application/json' \
  -d '{}')" || fail "Mobile login endpoint request failed"
[[ "$MOBILE_LOGIN_CODE" == "422" ]] || fail "Unexpected mobile login validation status: ${MOBILE_LOGIN_CODE}"

MOBILE_DASH_CODE="$(curl -sS -o /dev/null -w '%{http_code}' --max-time 20 "${APP_URL}/api/mobile/dashboard/summary")" \
  || fail "Mobile dashboard endpoint request failed"
[[ "$MOBILE_DASH_CODE" == "401" ]] || fail "Unexpected mobile dashboard unauthorized status: ${MOBILE_DASH_CODE}"
ok "Mobile API endpoints respond with expected auth/validation status"

echo
echo "Cutover completed successfully."
echo "Log: ${LOG_FILE}"
