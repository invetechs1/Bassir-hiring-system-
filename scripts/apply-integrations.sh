#!/usr/bin/env bash
set -Eeuo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

[[ -f artisan ]] || { echo "[FAIL] artisan not found"; exit 1; }
[[ -f .env ]] || { echo "[FAIL] .env not found"; exit 1; }

if ! command -v php >/dev/null 2>&1; then
  echo "[FAIL] php command is required"
  exit 1
fi

ENV_FILE="${1:-}"
if [[ -n "$ENV_FILE" ]]; then
  if [[ ! -f "$ENV_FILE" ]]; then
    echo "[FAIL] integration env file not found: $ENV_FILE"
    exit 1
  fi
  echo "[INFO] Loading integration values from: $ENV_FILE"
  set -a
  # shellcheck source=/dev/null
  source "$ENV_FILE"
  set +a
fi

php scripts/apply-integrations.php

