#!/usr/bin/env bash
# Pull origin/$DEPLOY_BRANCH and run deploy.sh (used by GitHub deploy webhook).
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
APP_ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd)"
cd "$APP_ROOT"

LOCK_FILE="${DEPLOY_LOCK_FILE:-/tmp/tryino-ecom-deploy.lock}"
LOG_FILE="${DEPLOY_LOG_FILE:-${APP_ROOT}/storage/logs/deploy.log}"
BRANCH="${DEPLOY_BRANCH:-production}"

mkdir -p "$(dirname "$LOG_FILE")"
echo "[$(date -u +%Y-%m-%dT%H:%M:%SZ)] pull-deploy invoked (pwd=${PWD}, branch=${BRANCH})" >>"$LOG_FILE" 2>&1

log() {
  echo "[$(date -u +%Y-%m-%dT%H:%M:%SZ)] $*"
}

run_deploy() {
  export DEPLOY_BRANCH="$BRANCH"
  bash "${SCRIPT_DIR}/deploy.sh" all
}

if [ ! -f artisan ]; then
  echo "ERROR: artisan not found in ${APP_ROOT}" >&2
  exit 1
fi

is_in_maintenance() {
  [ -f storage/framework/maintenance.php ] || [ -f storage/framework/down ]
}

run_locked() {
  git fetch origin "$BRANCH" --prune

  REMOTE="$(git rev-parse "origin/${BRANCH}")"
  LOCAL="$(git rev-parse HEAD 2>/dev/null || true)"

  if [ "${LOCAL}" = "${REMOTE}" ]; then
    if is_in_maintenance; then
      log "Recovery: at origin/${BRANCH} but site is still in maintenance — running finalize."
      export DEPLOY_BRANCH="$BRANCH"
      bash "${SCRIPT_DIR}/deploy.sh" finalize || {
        log "finalize failed; forcing php artisan up."
        php artisan up 2>/dev/null || true
        return 1
      }
      return 0
    fi

    log "Already at origin/${BRANCH} (${REMOTE}); nothing to do."
    return 0
  fi

  log "Deploying ${LOCAL:-<none>} → ${REMOTE}"
  run_deploy
  log "pull-deploy done"
}

if command -v flock >/dev/null 2>&1; then
  exec >>"$LOG_FILE" 2>&1
  (
    flock -n 9 || {
      log "Another deploy is running; skipping."
      exit 0
    }
    log "pull-deploy start (branch=${BRANCH})"
    run_locked
  ) 9>"$LOCK_FILE"
else
  {
    log "pull-deploy start (branch=${BRANCH}, no flock)"
    run_locked
  } >>"$LOG_FILE" 2>&1
fi
