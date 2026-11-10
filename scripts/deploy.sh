#!/usr/bin/env bash
# Runs ON THE SERVER, inside /root/bassir-laravel-shared-hosting.
# One command to go live with a freshly uploaded image tar:
#   scp bassir-laravel-shared-hosting-app.tar root@13.140.138.252:/root/bassir-laravel-shared-hosting/
#   ssh root@13.140.138.252 "cd /root/bassir-laravel-shared-hosting && bash deploy.sh"
#
# Backs up code + database, loads the image, recreates ONLY the app container (db untouched),
# migrates, re-indexes CVs, clears caches and checks the site answers before finishing.
set -euo pipefail

cd "$(dirname "$0")"

IMAGE_NAME="bassir-laravel-shared-hosting-app"
TAR_FILE="${IMAGE_NAME}.tar"
BACKUP_DIR="/root/backups"
STAMP="$(date +%Y%m%d%H%M%S)"
SITE_HOST="hiring-system.bassir.net"

if [ ! -f "$TAR_FILE" ]; then
    echo "Error: $TAR_FILE not found in $(pwd). Upload it before running this script." >&2
    exit 1
fi

artisan() { docker compose exec -T app php artisan "$@"; }

echo "==> [1/9] Backing up code and database to ${BACKUP_DIR}"
mkdir -p "$BACKUP_DIR"
tar czf "${BACKUP_DIR}/pre-deploy-code-${STAMP}.tar.gz" --exclude=vendor --exclude=storage --exclude="${TAR_FILE}" .
docker compose exec -T db sh -c 'mysqldump -u root -p"$MYSQL_ROOT_PASSWORD" --single-transaction --no-tablespaces "$MYSQL_DATABASE"' \
    | gzip > "${BACKUP_DIR}/pre-deploy-db-${STAMP}.sql.gz"
if [ ! -s "${BACKUP_DIR}/pre-deploy-db-${STAMP}.sql.gz" ] || [ "$(stat -c %s "${BACKUP_DIR}/pre-deploy-db-${STAMP}.sql.gz")" -lt 200 ]; then
    echo "Error: database backup looks empty. Aborting before anything is changed." >&2
    exit 1
fi
echo "    saved pre-deploy-code-${STAMP}.tar.gz and pre-deploy-db-${STAMP}.sql.gz"

echo "==> [2/9] Loading image from ${TAR_FILE}"
docker load -i "${TAR_FILE}"

echo "==> [3/9] Recreating app container from the loaded image (db untouched)"
docker compose up -d app

echo "==> [4/9] Waiting for the app container to be ready"
for i in $(seq 1 30); do
    if artisan --version >/dev/null 2>&1; then
        break
    fi
    if [ "$i" = "30" ]; then
        echo "Error: app container did not become ready. See: docker compose logs --tail 80 app" >&2
        exit 1
    fi
    sleep 2
done

echo "==> [5/9] Running database migrations"
artisan migrate --force

echo "==> [6/9] Indexing stored CVs for full-text search (skips CVs already indexed)"
artisan bassir:cv-reindex || echo "    warning: cv-reindex reported a problem; continuing (re-run it later)"

echo "==> [7/9] Filing CVs into the right specialty folders"
artisan bassir:cv-bank-reclassify || echo "    warning: reclassify reported a problem; continuing (re-run it later)"

echo "==> [8/9] Clearing caches"
artisan optimize:clear

echo "==> [9/9] Health check"
CODE="000"
for i in $(seq 1 10); do
    CODE="$(curl -s -o /dev/null -w '%{http_code}' -H "Host: ${SITE_HOST}" -H 'X-Forwarded-Proto: https' http://127.0.0.1:8083/login || true)"
    [ "$CODE" = "200" ] && break
    sleep 2
done
if [ "$CODE" != "200" ]; then
    echo "Error: site answered HTTP ${CODE}, expected 200. Check: docker compose logs --tail 80 app" >&2
    echo "Rollback: restore code from ${BACKUP_DIR}/pre-deploy-code-${STAMP}.tar.gz (database backup: pre-deploy-db-${STAMP}.sql.gz)." >&2
    exit 1
fi

echo "==> Cleaning up"
docker image prune -f >/dev/null
rm -f "${TAR_FILE}"

docker compose ps
echo "Deploy complete: https://${SITE_HOST}/ is live (HTTP ${CODE})."
