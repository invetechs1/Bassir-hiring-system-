#!/usr/bin/env bash
set -Eeuo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

CRON_FILE="$(mktemp)"

cat > "$CRON_FILE" <<CRON
# Bassir production operations cron
# Daily backup at 02:15
15 2 * * * cd $ROOT_DIR && BACKUP_ROOT=$ROOT_DIR/storage/backups RETENTION_DAYS=14 ./scripts/ops-backup.sh >> $ROOT_DIR/storage/logs/cron-backup.log 2>&1

# Health monitor every 5 minutes
*/5 * * * * cd $ROOT_DIR && ./scripts/ops-health-monitor.sh >> $ROOT_DIR/storage/logs/cron-health.log 2>&1
CRON

echo "Generated cron entries:"
echo "----------------------------------------"
cat "$CRON_FILE"
echo "----------------------------------------"

if [[ "${INSTALL_CRON:-false}" == "true" ]]; then
  command -v crontab >/dev/null 2>&1 || { echo "[FAIL] crontab command not found"; exit 1; }
  if crontab -l >/tmp/current_cron_bassir 2>/dev/null; then
    cat /tmp/current_cron_bassir "$CRON_FILE" | crontab -
  else
    crontab "$CRON_FILE"
  fi
  rm -f /tmp/current_cron_bassir
  echo "[OK] Cron installed"
else
  echo "[INFO] Dry run only. Set INSTALL_CRON=true to apply."
fi

rm -f "$CRON_FILE"

