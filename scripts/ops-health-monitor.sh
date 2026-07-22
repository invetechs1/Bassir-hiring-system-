#!/usr/bin/env bash
set -Eeuo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

[[ -f .env ]] || { echo "[FAIL] .env not found"; exit 1; }
command -v curl >/dev/null 2>&1 || { echo "[FAIL] curl command is required"; exit 1; }

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

APP_URL="${APP_URL_OVERRIDE:-$(env_value APP_URL)}"
APP_URL="${APP_URL%/}"
[[ -n "$APP_URL" ]] || { echo "[FAIL] APP_URL is empty"; exit 1; }

ALERT_WEBHOOK_URL="${ALERT_WEBHOOK_URL:-}"
STATUS_DIR="$ROOT_DIR/storage/logs"
mkdir -p "$STATUS_DIR"
STATUS_FILE="$STATUS_DIR/health-monitor-status.log"
TS="$(date +%Y-%m-%dT%H:%M:%S%z)"

health_body="$(curl -fsS --max-time 20 "$APP_URL/health" || true)"
if [[ -z "$health_body" ]]; then
  echo "[$TS] FAIL health endpoint unreachable" | tee -a "$STATUS_FILE"
  if [[ -n "$ALERT_WEBHOOK_URL" ]]; then
    curl -sS -X POST "$ALERT_WEBHOOK_URL" -H 'Content-Type: application/json' \
      -d "{\"service\":\"bassir-web\",\"status\":\"down\",\"time\":\"$TS\",\"message\":\"health endpoint unreachable\"}" >/dev/null || true
  fi
  exit 1
fi

if [[ "$health_body" != *'"database":"ok"'* ]] || [[ "$health_body" != *'"storage":"ok"'* ]]; then
  echo "[$TS] FAIL unhealthy response: $health_body" | tee -a "$STATUS_FILE"
  if [[ -n "$ALERT_WEBHOOK_URL" ]]; then
    curl -sS -X POST "$ALERT_WEBHOOK_URL" -H 'Content-Type: application/json' \
      -d "{\"service\":\"bassir-web\",\"status\":\"degraded\",\"time\":\"$TS\",\"message\":\"$health_body\"}" >/dev/null || true
  fi
  exit 1
fi

echo "[$TS] OK $health_body" | tee -a "$STATUS_FILE"
exit 0

