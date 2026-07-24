#!/usr/bin/env bash
# Runs on your LOCAL machine, from the project root.
# Builds the Laravel app image, ships it to the server as a tar, and triggers
# the remote load-and-run script. Requires Docker locally and SSH access to
# the server (key-based auth recommended so this can run unattended).
set -euo pipefail

IMAGE_NAME="bassir-laravel-shared-hosting-app"
IMAGE_TAG="latest"
TAR_FILE="${IMAGE_NAME}.tar"

SERVER_USER="root"
SERVER_HOST="13.140.138.252"
SERVER_PATH="/root/bassir-laravel-shared-hosting"

echo "==> Removing old local image (if present)"
docker rmi "${IMAGE_NAME}:${IMAGE_TAG}" 2>/dev/null || true

echo "==> Building new image"
docker build -t "${IMAGE_NAME}:${IMAGE_TAG}" .

echo "==> Saving image to ${TAR_FILE}"
docker save -o "${TAR_FILE}" "${IMAGE_NAME}:${IMAGE_TAG}"

echo "==> Uploading ${TAR_FILE} to ${SERVER_HOST}"
scp "${TAR_FILE}" "${SERVER_USER}@${SERVER_HOST}:${SERVER_PATH}/${TAR_FILE}"

echo "==> Running remote deploy script"
ssh "${SERVER_USER}@${SERVER_HOST}" "cd ${SERVER_PATH} && bash server-deploy-image.sh"

echo "==> Cleaning up local tar file"
rm -f "${TAR_FILE}"

echo "Done."
