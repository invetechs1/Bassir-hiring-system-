#!/usr/bin/env bash
# Runs ON THE SERVER, inside /root/bassir-laravel-shared-hosting.
# Loads a freshly shipped image tar and recreates the app container from it.
# The db service and its volumes are untouched.
set -euo pipefail

cd "$(dirname "$0")"

IMAGE_NAME="bassir-laravel-shared-hosting-app"
IMAGE_TAG="latest"
TAR_FILE="${IMAGE_NAME}.tar"

if [ ! -f "$TAR_FILE" ]; then
    echo "Error: $TAR_FILE not found in $(pwd). Upload it before running this script." >&2
    exit 1
fi

echo "==> Loading image from ${TAR_FILE}"
docker load -i "${TAR_FILE}"

echo "==> Recreating app container from the loaded image (db untouched)"
docker compose up -d app

echo "==> Removing dangling images left behind by the retag"
docker image prune -f

echo "==> Cleaning up tar file"
rm -f "${TAR_FILE}"

echo "==> Current status"
docker compose ps
