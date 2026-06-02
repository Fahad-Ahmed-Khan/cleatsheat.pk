#!/usr/bin/env bash
set -euo pipefail

BRANCH="${DEPLOY_BRANCH:-main}"

if [ ! -f artisan ]; then
  echo "ERROR: artisan not found. Run this from your Laravel project root."
  exit 1
fi

if ! command -v php >/dev/null 2>&1; then
  echo "ERROR: php not found in PATH."
  exit 1
fi

if ! command -v composer >/dev/null 2>&1; then
  echo "ERROR: composer not found in PATH."
  exit 1
fi

php artisan down --render="errors::503" --retry=60 || true

git fetch --all --prune
git reset --hard "origin/${BRANCH}"

composer install --no-dev --optimize-autoloader --no-interaction --no-progress

php artisan migrate --force

php artisan optimize:clear
php artisan config:cache
php artisan route:cache || true
php artisan view:cache

php artisan up || true

echo "Deploy complete."
