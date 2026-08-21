#!/usr/bin/env bash
set -euo pipefail

MKAUTH_DIR="${MKAUTH_DIR:-/opt/mk-auth}"
DB_HOST="${MKAUTH_DB_HOST:-127.0.0.1}"
DB_USER="${MKAUTH_DB_USER:-root}"
DB_PASS="${MKAUTH_DB_PASS:-vertrigo}"
DB_NAME="${MKAUTH_DB_NAME:-mkradius}"
INSTALL_CRON="${INSTALL_CRON:-1}"
RUN_CONCILIADOR_NOW="${RUN_CONCILIADOR_NOW:-1}"
ENABLE_BAIXA_MANUAL_SICREDI="${ENABLE_BAIXA_MANUAL_SICREDI:-0}"
RUN_BAIXA_MANUAL_NOW="${RUN_BAIXA_MANUAL_NOW:-0}"
PHP_BIN="${PHP_BIN:-/opt/php8/bin/php}"
WEB_USER="${WEB_USER:-www-data}"
WEB_GROUP="${WEB_GROUP:-www-data}"

if [ -n "${BASH_SOURCE[0]:-}" ] && [ -f "${BASH_SOURCE[0]}" ]; then
  ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
else
  ROOT_DIR="$(pwd)"
fi
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

if [ ! -d "$ROOT_DIR/addons/rel_conciliacao_sicredi" ] || [ ! -f "$ROOT_DIR/jobs/SICREDIAPI/conciliar_sicredi_desconto.php" ] || [ ! -f "$ROOT_DIR/jobs/SICREDIAPI/enviar_baixa_manual_sicredi.php" ]; then
  echo "Arquivos do instalador nao encontrados em $ROOT_DIR." >&2
  echo "Use o bootstrap via curl:" >&2
  echo "curl -fsSL https://raw.githubusercontent.com/brsxdlols/mkauth-sicredi/main/installers/github-install.sh | sudo bash" >&2
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
cp -a "$ROOT_DIR/jobs/SICREDIAPI/enviar_baixa_manual_sicredi.php" "$JOB_DIR/enviar_baixa_manual_sicredi.php"
cp -a "$ROOT_DIR/jobs/SICREDIAPI/run_enviar_baixa_manual_sicredi.sh" "$JOB_DIR/run_enviar_baixa_manual_sicredi.sh"

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
sed -i "s/const DB_HOST = '.*';/const DB_HOST = '$DB_HOST_ESC';/" "$JOB_DIR/enviar_baixa_manual_sicredi.php"
sed -i "s/const DB_USER = '.*';/const DB_USER = '$DB_USER_ESC';/" "$JOB_DIR/enviar_baixa_manual_sicredi.php"
sed -i "s/const DB_PASS = '.*';/const DB_PASS = '$DB_PASS_ESC';/" "$JOB_DIR/enviar_baixa_manual_sicredi.php"
sed -i "s/const DB_NAME = '.*';/const DB_NAME = '$DB_NAME_ESC';/" "$JOB_DIR/enviar_baixa_manual_sicredi.php"

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

install_menu_lines() {
  local menu_file=""
  local file
  local line_baixas="add_menu.financeiro('{\"plink\": \"' + minha_url + 'addons/rel_conciliacao_sicredi/\", \"ptext\": \"Sicredi API - Baixas\"}');"
  local line_logs="add_menu.financeiro('{\"plink\": \"' + minha_url + 'addons/log_sicredi_api/\", \"ptext\": \"Sicredi API - Logs\"}');"

  for file in "$ADMIN_ADDONS/addon.js" "$ADMIN_ADDONS/addon_aplicativos.js"; do
    [ -f "$file" ] || continue
    cp -a "$file" "$file.bak_sicredi_$STAMP"
    sed -i \
      -e '/addons\/rel_conciliacao_sicredi\//d' \
      -e '/addons\/log_sicredi_api\//d' \
      -e '/Sicredi API - Baixas/d' \
      -e '/Sicredi API - Logs/d' \
      "$file"
    [ -n "$menu_file" ] || menu_file="$file"
  done

  if [ -z "$menu_file" ]; then
    menu_file="$ADMIN_ADDONS/addon.js"
    touch "$menu_file"
  fi

  printf "\n%s\n%s\n" "$line_baixas" "$line_logs" >> "$menu_file"
  echo "Menu Sicredi instalado em: $menu_file"
}

install_menu_lines

chmod +x "$JOB_DIR/conciliar_sicredi_desconto.php" "$JOB_DIR/run_conciliar_sicredi_desconto.sh" "$JOB_DIR/enviar_baixa_manual_sicredi.php" "$JOB_DIR/run_enviar_baixa_manual_sicredi.sh"
chown -R "$WEB_USER:$WEB_GROUP" "$ADMIN_ADDONS/rel_conciliacao_sicredi" "$ADMIN_ADDONS/log_sicredi_api" || true
find "$ADMIN_ADDONS/rel_conciliacao_sicredi" "$ADMIN_ADDONS/log_sicredi_api" -type f -exec chmod 640 {} \;
find "$ADMIN_ADDONS/rel_conciliacao_sicredi" "$ADMIN_ADDONS/log_sicredi_api" -type d -exec chmod 750 {} \;

"$PHP_BIN" -l "$ADMIN_ADDONS/rel_conciliacao_sicredi/index.php"
"$PHP_BIN" -l "$ADMIN_ADDONS/log_sicredi_api/index.php"
"$PHP_BIN" -l "$JOB_DIR/conciliar_sicredi_desconto.php"
"$PHP_BIN" -l "$JOB_DIR/enviar_baixa_manual_sicredi.php"

if [ "$INSTALL_CRON" = "1" ]; then
  cat > /etc/cron.d/conciliar_sicredi_desconto <<CRON
*/5 * * * * root $JOB_DIR/run_conciliar_sicredi_desconto.sh
CRON
  chmod 644 /etc/cron.d/conciliar_sicredi_desconto
  echo "Cron instalado: /etc/cron.d/conciliar_sicredi_desconto"

  if [ "$ENABLE_BAIXA_MANUAL_SICREDI" = "1" ]; then
    cat > /etc/cron.d/enviar_baixa_manual_sicredi <<CRON
*/10 * * * * root $JOB_DIR/run_enviar_baixa_manual_sicredi.sh
CRON
    chmod 644 /etc/cron.d/enviar_baixa_manual_sicredi
    echo "Cron instalado: /etc/cron.d/enviar_baixa_manual_sicredi"
  else
    rm -f /etc/cron.d/enviar_baixa_manual_sicredi
    echo "Cron de baixa manual Sicredi nao ativado. Use ENABLE_BAIXA_MANUAL_SICREDI=1 para ativar."
  fi
else
  echo "Cron ignorado por INSTALL_CRON=0"
fi

mkdir -p /var/log/mk-auth

if command -v mysql >/dev/null 2>&1; then
  mysql -h "$DB_HOST" -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "
    SET @idx := (
      SELECT COUNT(1)
        FROM information_schema.STATISTICS
       WHERE TABLE_SCHEMA = DATABASE()
         AND TABLE_NAME = 'sis_notificacoes'
         AND INDEX_NAME = 'idx_sicredi_data_resposta'
    );
    SET @sql := IF(@idx = 0,
      'ALTER TABLE sis_notificacoes ADD INDEX idx_sicredi_data_resposta (servico, data, resposta)',
      'SELECT 1'
    );
    PREPARE stmt FROM @sql;
    EXECUTE stmt;
    DEALLOCATE PREPARE stmt;
  " >/dev/null 2>&1 || echo "Aviso: nao foi possivel criar indice idx_sicredi_data_resposta"
fi

echo "== Teste dry-run do conciliador =="
"$PHP_BIN" "$JOB_DIR/conciliar_sicredi_desconto.php" --days=15 || true
echo "== Teste dry-run da baixa manual Sicredi =="
"$PHP_BIN" "$JOB_DIR/enviar_baixa_manual_sicredi.php" --days=15 --limit=20 || true

if [ "$RUN_CONCILIADOR_NOW" = "1" ]; then
  echo "== Execucao inicial do conciliador =="
  "$PHP_BIN" "$JOB_DIR/conciliar_sicredi_desconto.php" --days=15 --apply || true
else
  echo "Execucao inicial ignorada por RUN_CONCILIADOR_NOW=0"
fi

if [ "$RUN_BAIXA_MANUAL_NOW" = "1" ]; then
  echo "== Execucao inicial da baixa manual Sicredi =="
  "$PHP_BIN" "$JOB_DIR/enviar_baixa_manual_sicredi.php" --days=15 --limit=20 --apply || true
else
  echo "Baixa manual Sicredi nao enviada agora. Use RUN_BAIXA_MANUAL_NOW=1 para aplicar na instalacao."
fi

echo "== Instalacao concluida =="
echo "Relatorio: /admin/addons/rel_conciliacao_sicredi/"
echo "Logs:      /admin/addons/log_sicredi_api/"
echo "Baixa manual: $JOB_DIR/enviar_baixa_manual_sicredi.php"
echo "Menu: use Ctrl+F5 no navegador se nao aparecer imediatamente."
