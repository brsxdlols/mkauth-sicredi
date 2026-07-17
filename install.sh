#!/usr/bin/env bash
set -euo pipefail

MKAUTH_DIR="${MKAUTH_DIR:-/opt/mk-auth}"
DB_HOST="${MKAUTH_DB_HOST:-127.0.0.1}"
DB_USER="${MKAUTH_DB_USER:-root}"
DB_PASS="${MKAUTH_DB_PASS:-vertrigo}"
DB_NAME="${MKAUTH_DB_NAME:-mkradius}"
INSTALL_CRON="${INSTALL_CRON:-1}"
PHP_BIN="${PHP_BIN:-/opt/php8/bin/php}"
WEB_USER="${WEB_USER:-www-data}"
WEB_GROUP="${WEB_GROUP:-www-data}"

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ADMIN_ADDONS="$MKAUTH_DIR/admin/addons"
JOB_DIR="$MKAUTH_DIR/jobs/SICREDIAPI"
STAMP="$(date +%Y%m%d%H%M%S)"

if [ "$(id -u)" -ne 0 ]; then
  echo "Execute como root: sudo bash install.sh" >&2
  exit 1
fi

if [ ! -d "$MKAUTH_DIR" ]; then
  echo "Diretorio MK Auth nao encontrado: $MKAUTH_DIR" >&2
  exit 1
fi

if [ ! -x "$PHP_BIN" ]; then
  PHP_BIN="$(command -v php || true)"
fi
if [ -z "$PHP_BIN" ]; then
  echo "PHP nao encontrado. Informe PHP_BIN=/caminho/php" >&2
  exit 1
fi

echo "== Instalando addons Sicredi em $MKAUTH_DIR =="
mkdir -p "$ADMIN_ADDONS/rel_conciliacao_sicredi" "$ADMIN_ADDONS/log_sicredi_api" "$JOB_DIR"

cp -a "$ROOT_DIR/addons/rel_conciliacao_sicredi/." "$ADMIN_ADDONS/rel_conciliacao_sicredi/"
cp -a "$ROOT_DIR/addons/log_sicredi_api/." "$ADMIN_ADDONS/log_sicredi_api/"
cp -a "$ROOT_DIR/jobs/SICREDIAPI/conciliar_sicredi_desconto.php" "$JOB_DIR/conciliar_sicredi_desconto.php"
cp -a "$ROOT_DIR/jobs/SICREDIAPI/run_conciliar_sicredi_desconto.sh" "$JOB_DIR/run_conciliar_sicredi_desconto.sh"

# Ajusta credenciais do banco no job, mantendo padrao se variaveis nao forem informadas.
escape_sed() {
  printf '%s' "$1" | sed 's/[\/&]/\\&/g'
}
DB_HOST_ESC="$(escape_sed "$DB_HOST")"
DB_USER_ESC="$(escape_sed "$DB_USER")"
DB_PASS_ESC="$(escape_sed "$DB_PASS")"
DB_NAME_ESC="$(escape_sed "$DB_NAME")"
sed -i "s/const DB_HOST = '.*';/const DB_HOST = '$DB_HOST_ESC';/" "$JOB_DIR/conciliar_sicredi_desconto.php"
sed -i "s/const DB_USER = '.*';/const DB_USER = '$DB_USER_ESC';/" "$JOB_DIR/conciliar_sicredi_desconto.php"
sed -i "s/const DB_PASS = '.*';/const DB_PASS = '$DB_PASS_ESC';/" "$JOB_DIR/conciliar_sicredi_desconto.php"
sed -i "s/const DB_NAME = '.*';/const DB_NAME = '$DB_NAME_ESC';/" "$JOB_DIR/conciliar_sicredi_desconto.php"

# addons.class.php compatibilidade entre versoes do MK Auth.
for addon in rel_conciliacao_sicredi log_sicredi_api; do
  if [ -f "$MKAUTH_DIR/include/addons.inc.hhvm" ]; then
    ln -sfn "$MKAUTH_DIR/include/addons.inc.hhvm" "$ADMIN_ADDONS/$addon/addons.class.php"
  elif [ -f "$ADMIN_ADDONS/dashboard/addons.class.php" ]; then
    cp -a "$ADMIN_ADDONS/dashboard/addons.class.php" "$ADMIN_ADDONS/$addon/addons.class.php"
  elif [ -f "$ADMIN_ADDONS/rel_caixa/addons.class.php" ]; then
    cp -a "$ADMIN_ADDONS/rel_caixa/addons.class.php" "$ADMIN_ADDONS/$addon/addons.class.php"
  fi
done

add_menu_line() {
  local file="$1"
  local line_baixas="add_menu.financeiro('{\"plink\": \"' + minha_url + 'addons/rel_conciliacao_sicredi/\", \"ptext\": \"Sicredi API - Baixas\"}');"
  local line_logs="add_menu.financeiro('{\"plink\": \"' + minha_url + 'addons/log_sicredi_api/\", \"ptext\": \"Sicredi API - Logs\"}');"
  [ -f "$file" ] || return 0
  cp -a "$file" "$file.bak_sicredi_$STAMP"
  grep -v 'addons/rel_conciliacao_sicredi/' "$file" | grep -v 'addons/log_sicredi_api/' > "$file.tmp_sicredi"
  mv "$file.tmp_sicredi" "$file"
  printf "\n%s\n%s\n" "$line_baixas" "$line_logs" >> "$file"
}

add_menu_line "$ADMIN_ADDONS/addon.js"
add_menu_line "$ADMIN_ADDONS/addon_aplicativos.js"

chmod +x "$JOB_DIR/conciliar_sicredi_desconto.php" "$JOB_DIR/run_conciliar_sicredi_desconto.sh"
chown -R "$WEB_USER:$WEB_GROUP" "$ADMIN_ADDONS/rel_conciliacao_sicredi" "$ADMIN_ADDONS/log_sicredi_api" || true
find "$ADMIN_ADDONS/rel_conciliacao_sicredi" "$ADMIN_ADDONS/log_sicredi_api" -type f -exec chmod 640 {} \;
find "$ADMIN_ADDONS/rel_conciliacao_sicredi" "$ADMIN_ADDONS/log_sicredi_api" -type d -exec chmod 750 {} \;

"$PHP_BIN" -l "$ADMIN_ADDONS/rel_conciliacao_sicredi/index.php"
"$PHP_BIN" -l "$ADMIN_ADDONS/log_sicredi_api/index.php"
"$PHP_BIN" -l "$JOB_DIR/conciliar_sicredi_desconto.php"

if [ "$INSTALL_CRON" = "1" ]; then
  cat > /etc/cron.d/conciliar_sicredi_desconto <<CRON
*/5 * * * * root $JOB_DIR/run_conciliar_sicredi_desconto.sh
CRON
  chmod 644 /etc/cron.d/conciliar_sicredi_desconto
  echo "Cron instalado: /etc/cron.d/conciliar_sicredi_desconto"
else
  echo "Cron ignorado por INSTALL_CRON=0"
fi

mkdir -p /var/log/mk-auth

echo "== Teste dry-run do conciliador =="
"$PHP_BIN" "$JOB_DIR/conciliar_sicredi_desconto.php" --days=15 || true

echo "== Instalacao concluida =="
echo "Relatorio: /admin/addons/rel_conciliacao_sicredi/"
echo "Logs:      /admin/addons/log_sicredi_api/"
echo "Menu: use Ctrl+F5 no navegador se nao aparecer imediatamente."
