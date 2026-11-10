#!/usr/bin/env bash
# Kept for build-and-ship.sh; the full deploy logic lives in deploy.sh.
set -euo pipefail
cd "$(dirname "$0")"
exec bash deploy.sh
