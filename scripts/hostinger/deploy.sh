#!/usr/bin/env bash
set -euo pipefail

BRANCH="${DEPLOY_BRANCH:-master}"

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

composer install --no-dev --optimize-autoloader --no-interaction --no-progress --ignore-platform-reqs

if [ ! -f public/build/manifest.json ]; then
  if command -v npm >/dev/null 2>&1; then
    echo "Building frontend assets (Vite)..."
    npm ci --no-audit --no-fund
    npm run build
  else
    echo "ERROR: public/build/manifest.json is missing."
    echo "Run 'npm ci && npm run build' locally, upload public/build to the server,"
    echo "or deploy via GitHub Actions (builds assets before SSH deploy)."
    exit 1
  fi
fi

php artisan migrate --force

# Generate responsive WebP variants for any product images missing them (idempotent, non-fatal).
php artisan products:generate-image-variants || true

# Generate responsive WebP variants for the hero/LCP image (idempotent, non-fatal).
php artisan storefront:generate-hero-variants || true

php artisan optimize:clear
php artisan config:cache
php artisan route:cache || true
php artisan view:cache

php artisan up || true

echo "Deploy complete."
