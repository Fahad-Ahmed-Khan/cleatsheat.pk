#!/usr/bin/env bash
set -euo pipefail

BRANCH="${DEPLOY_BRANCH:-master}"

# Stage to run:
#   prepare  -> maintenance mode, sync code, composer, migrate (run BEFORE uploading public/build)
#   finalize -> rebuild caches, generate image variants, leave maintenance mode (run AFTER uploading public/build)
#   all      -> prepare + finalize in one go (default; assumes public/build is already present locally)
STAGE="${1:-all}"

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

run_prepare() {
  php artisan down --render="errors::503" --retry=60 || true

  git fetch --all --prune
  git reset --hard "origin/${BRANCH}"

  composer install --no-dev --optimize-autoloader --no-interaction --no-progress --ignore-platform-reqs

  php artisan migrate --force
}

run_finalize() {
  # public/build is deployed via rsync (it is gitignored), so it must exist by now.
  if [ ! -f public/build/manifest.json ]; then
    if command -v npm >/dev/null 2>&1; then
      echo "public/build/manifest.json missing — building frontend assets (Vite) as a fallback..."
      npm ci --no-audit --no-fund
      npm run build
    else
      echo "ERROR: public/build/manifest.json is missing and npm is unavailable."
      echo "Upload public/build to the server (CI rsync) or build it locally first."
      exit 1
    fi
  fi

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
}

case "$STAGE" in
  prepare)
    run_prepare
    ;;
  finalize)
    run_finalize
    ;;
  all)
    run_prepare
    run_finalize
    ;;
  *)
    echo "ERROR: unknown stage '${STAGE}'. Use: prepare | finalize | all"
    exit 1
    ;;
esac
