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

# shellcheck source=resolve-php.sh
. "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/resolve-php.sh"

if [ ! -f artisan ]; then
  echo "ERROR: artisan not found. Run this from your Laravel project root."
  exit 1
fi

echo "Using PHP: ${PHP_BIN} ($("${PHP_BIN}" -r 'echo PHP_VERSION;'))"

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

# Read PUBLIC_DISK_DRIVER from .env (not exported to the shell during deploy).
public_disk_driver() {
  local driver="local"
  if [ -f .env ]; then
    driver="$(grep -E '^PUBLIC_DISK_DRIVER=' .env 2>/dev/null | tail -n 1 | cut -d= -f2- | tr -d '"' | tr -d "'" | tr -d '\r' | sed 's/^[[:space:]]*//;s/[[:space:]]*$//')"
  fi
  [ -z "${driver}" ] && driver="local"
  echo "${driver}"
}

# True when uploads use local disk (needs public/storage symlink), not B2/S3.
uses_local_public_disk() {
  [ "$(public_disk_driver)" != "s3" ]
}

# Backup gitignored local uploads before reset (migration safety net; skip when on B2).
backup_local_public_uploads() {
  if ! uses_local_public_disk; then
    return 0
  fi

  local src="storage/app/public"
  if [ ! -d "${src}" ] || [ -z "$(find "${src}" -mindepth 1 -not -name '.gitignore' -print -quit 2>/dev/null)" ]; then
    return 0
  fi

  UPLOAD_BACKUP_DIR="$(mktemp -d /tmp/tryino-uploads-backup.XXXXXX)"
  echo "Backing up local public uploads to ${UPLOAD_BACKUP_DIR}..."
  cp -a "${src}/." "${UPLOAD_BACKUP_DIR}/"
}

restore_local_public_uploads() {
  if [ -z "${UPLOAD_BACKUP_DIR:-}" ] || [ ! -d "${UPLOAD_BACKUP_DIR}" ]; then
    return 0
  fi

  if ! uses_local_public_disk; then
    rm -rf "${UPLOAD_BACKUP_DIR}"
    unset UPLOAD_BACKUP_DIR
    return 0
  fi

  mkdir -p storage/app/public
  echo "Restoring local public uploads from backup..."
  cp -a "${UPLOAD_BACKUP_DIR}/." storage/app/public/
  rm -rf "${UPLOAD_BACKUP_DIR}"
  unset UPLOAD_BACKUP_DIR
}

log_public_disk_status() {
  local driver file_count
  driver="$(public_disk_driver)"
  if uses_local_public_disk; then
    file_count="$(find storage/app/public -type f ! -name '.gitignore' 2>/dev/null | wc -l | tr -d ' ')"
    echo "Public disk: local (${file_count} file(s) under storage/app/public)"
  else
    echo "Public disk: ${driver} (uploads stored off-server)"
  fi
}

run_prepare() {
  php artisan down --render="errors::503" --retry=60 || true
  MAINTENANCE_ENABLED=1

  backup_local_public_uploads

  git fetch origin "+refs/heads/${BRANCH}:refs/remotes/origin/${BRANCH}" --prune
  git reset --hard "origin/${BRANCH}"

  restore_local_public_uploads

  # Stale package discovery can reference dev-only providers (e.g. laravel/pail) after --no-dev.
  rm -f bootstrap/cache/packages.php bootstrap/cache/services.php

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


  # Convert legacy full URLs in the DB to disk-relative paths (idempotent, non-fatal).
  php artisan storage:normalize-paths || true

  # Backfill denormalized product search_text after migrations (idempotent, non-fatal).
  php artisan catalog:rebuild-search-index || true

  # Generate responsive WebP variants for any product images missing them (idempotent, non-fatal).
  php artisan products:generate-image-variants || true

  # Generate responsive WebP variants for the hero/LCP image (idempotent, non-fatal).
  php artisan storefront:generate-hero-variants || true

  # Local public disk only — B2/S3 serves files directly (no symlink).
  if uses_local_public_disk; then
    php artisan storage:link --force
  fi

  log_public_disk_status

  php artisan optimize:clear
  php artisan config:cache
  # Do not route:cache — Ziggy @routes('store') breaks with cached routes on Hostinger.
  php artisan route:clear
  php artisan view:cache

  # LiteSpeed caches PHP bytecode; restart workers so web requests load new classes/migrations logic.
  killall lsphp 2>/dev/null || true
  killall lsphp82 2>/dev/null || true
  killall lsphp83 2>/dev/null || true

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
