#!/bin/bash
# Deploy steps that run AFTER `git pull`.
# Stub outside the repo (e.g. ~/deploy-cp.sh) does the cd + git pull
# and `exec`s into this script. Versioned in the repo so deploy
# evolutions (queue restart, opcache reset, smoke checks) ship as commits
# instead of manual edits on the server.

set -euo pipefail

echo "--- Migrating database ---"
php artisan migrate --force

echo "--- Fixing permissions ---"
sudo chown -R "$USER":www-data .
sudo chmod -R 775 storage bootstrap/cache

echo "--- Deploy done ---"
