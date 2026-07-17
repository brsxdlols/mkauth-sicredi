#!/bin/bash
set -euo pipefail

LOG_DIR="/var/log/mk-auth"
LOG_FILE="$LOG_DIR/conciliar_sicredi_desconto.log"
LOCK_FILE="/tmp/conciliar_sicredi_desconto.lock"

mkdir -p "$LOG_DIR"

{
  echo "===== $(date '+%Y-%m-%d %H:%M:%S') ====="
  /usr/bin/flock -n "$LOCK_FILE" /opt/php8/bin/php /opt/mk-auth/jobs/SICREDIAPI/conciliar_sicredi_desconto.php --days=15 --apply
} >> "$LOG_FILE" 2>&1
