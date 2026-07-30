#!/bin/bash
set -euo pipefail

LOG_DIR="/var/log/mk-auth"
LOG_FILE="$LOG_DIR/enviar_baixa_manual_sicredi.log"
LOCK_FILE="/tmp/enviar_baixa_manual_sicredi.lock"

mkdir -p "$LOG_DIR"

{
  echo "===== $(date '+%Y-%m-%d %H:%M:%S') ====="
  /usr/bin/flock -n "$LOCK_FILE" /opt/php8/bin/php /opt/mk-auth/jobs/SICREDIAPI/enviar_baixa_manual_sicredi.php --days=15 --limit=20 --apply
} >> "$LOG_FILE" 2>&1
