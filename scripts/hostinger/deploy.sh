#!/usr/bin/env bash
set -euo pipefail

BRANCH="${DEPLOY_BRANCH:-production}"

# Stage to run:
#   prepare  -> maintenance mode, sync code from origin/$BRANCH, composer, migrate
#   finalize -> rebuild caches, generate image variants, leave maintenance mode
#   all      -> prepare + finalize (used by cron-deploy.sh on Hostinger)
#
# CI pushes the `production` branch with Vite build artifacts (no npm on Hostinger).
# Set DEPLOY_BRANCH=master only for manual deploys from a machine that builds assets locally.
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
  # public/build is committed on the `production` branch by GitHub Actions (gitignored on master).
  if [ ! -f public/build/manifest.json ]; then
    if command -v npm >/dev/null 2>&1; then
      echo "public/build/manifest.json missing — building frontend assets (Vite) as a fallback..."
      npm ci --no-audit --no-fund
      npm run build
    else
      echo "ERROR: public/build/manifest.json is missing and npm is unavailable."
      echo "Deploy the production branch from CI, or build assets locally before running deploy.sh."
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
