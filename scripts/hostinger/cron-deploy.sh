#!/usr/bin/env bash
# Optional fallback if the GitHub deploy webhook is unavailable.
# Prefer the webhook: POST /webhooks/github/deploy (see config/deploy.php).
exec "$(dirname "$0")/pull-deploy.sh"
