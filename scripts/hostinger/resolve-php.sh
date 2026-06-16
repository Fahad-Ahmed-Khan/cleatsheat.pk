#!/usr/bin/env bash
# Resolve PHP 8.3+ for Hostinger (default SSH `php` is often 8.2).
# Source from deploy scripts: . "$(dirname "$0")/resolve-php.sh"

resolve_hostinger_php() {
  if [ -n "${PHP_BIN:-}" ] && [ -x "${PHP_BIN}" ]; then
    echo "${PHP_BIN}"
    return 0
  fi

  local candidate major minor
  for candidate in \
    /opt/alt/php83/usr/bin/php \
    "$(command -v php83 2>/dev/null || true)" \
    "$(command -v php 2>/dev/null || true)"
  do
    [ -z "${candidate}" ] || [ ! -x "${candidate}" ] && continue
    major="$("${candidate}" -r 'echo PHP_MAJOR_VERSION;' 2>/dev/null || echo 0)"
    minor="$("${candidate}" -r 'echo PHP_MINOR_VERSION;' 2>/dev/null || echo 0)"
    if [ "${major}" -gt 8 ] || { [ "${major}" -eq 8 ] && [ "${minor}" -ge 3 ]; }; then
      echo "${candidate}"
      return 0
    fi
  done

  echo "ERROR: PHP 8.3+ required (Laravel 13). Try: export PHP_BIN=/opt/alt/php83/usr/bin/php" >&2
  return 1
}

PHP_BIN="$(resolve_hostinger_php)" || exit 1

php() {
  "${PHP_BIN}" "$@"
}

composer() {
  local composer_bin
  composer_bin="$(command -v composer 2>/dev/null || true)"
  if [ -z "${composer_bin}" ]; then
    echo "ERROR: composer not found in PATH." >&2
    return 1
  fi
  "${PHP_BIN}" "${composer_bin}" "$@"
}

export PHP_BIN
