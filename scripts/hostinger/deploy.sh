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

# Cron / webhook / `deploy:run-pending` often run without HOME; Composer 2.x requires it.
if [ -z "${HOME:-}" ]; then
  _home_from_passwd="$(getent passwd "$(id -un)" 2>/dev/null | cut -d: -f6 || true)"
  if [ -n "${_home_from_passwd}" ] && [ -d "${_home_from_passwd}" ]; then
    export HOME="${_home_from_passwd}"
  elif [ -d "/home/$(id -un)" ]; then
    export HOME="/home/$(id -un)"
  else
    export HOME="/tmp"
  fi
  unset _home_from_passwd
fi
export COMPOSER_HOME="${COMPOSER_HOME:-${HOME}/.composer}"
mkdir -p "${COMPOSER_HOME}"

# If prepare/finalize fails after `artisan down`, always bring the site back up.
MAINTENANCE_ENABLED=0

ensure_site_up() {
  if [ "${MAINTENANCE_ENABLED}" -eq 1 ]; then
    echo "Deploy failed or was interrupted — running php artisan up..."
    php artisan up 2>/dev/null || true
    MAINTENANCE_ENABLED=0
  fi
}

send_deploy_notification() {
  local status="$1"
  local detail="$2"
  php artisan deploy:notify "$status" --source=production --detail="$detail" 2>/dev/null || true
}

on_exit() {
  local exit_code=$?
  ensure_site_up
  if [ "$exit_code" -ne 0 ]; then
    send_deploy_notification failed "Branch ${BRANCH}, stage ${STAGE}. Exit code ${exit_code}."
  fi
  exit "$exit_code"
}

trap on_exit EXIT

is_in_maintenance() {
  [ -f storage/framework/maintenance.php ] || [ -f storage/framework/down ]
}

run_prepare() {
  php artisan down --render="errors::503" --retry=60 || true
  MAINTENANCE_ENABLED=1

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


  # Backfill denormalized product search_text after migrations (idempotent, non-fatal).
  php artisan catalog:rebuild-search-index || true

  # Generate responsive WebP variants for any product images missing them (idempotent, non-fatal).
  php artisan products:generate-image-variants || true

  # Generate responsive WebP variants for the hero/LCP image (idempotent, non-fatal).
  php artisan storefront:generate-hero-variants || true

  php artisan optimize:clear
  php artisan config:cache
  # Do not route:cache — Ziggy @routes('store') breaks with cached routes on Hostinger.
  php artisan route:clear
  php artisan view:cache

  php artisan up || true
  MAINTENANCE_ENABLED=0

  echo "Deploy complete."
}

notify_success() {
  send_deploy_notification success "Branch ${BRANCH}, stage ${STAGE} completed."
}

case "$STAGE" in
  prepare)
    run_prepare
    notify_success
    ;;
  finalize)
    run_finalize
    notify_success
    ;;
  all)
    run_prepare
    run_finalize
    notify_success
    ;;
  *)
    echo "ERROR: unknown stage '${STAGE}'. Use: prepare | finalize | all"
    exit 1
    ;;
esac
