# mkauth-sicredi

Instalador dos addons e conciliador Sicredi para MK Auth.

## O que instala

- `Sicredi API - Baixas`: relatório financeiro das baixas feitas pela API Sicredi, agrupado por dia.
- `Sicredi API - Logs`: tela web para troubleshooting dos webhooks recebidos em `sis_notificacoes`.
- Job `conciliar_sicredi_desconto.php`: concilia webhooks Sicredi `REJEITADO` quando o valor liquidado bate com valor líquido do cliente, considerando desconto/acréscimo.
- Cron a cada 5 minutos para executar a conciliação.
- Entradas no menu Financeiro do MK Auth.

## Instalação no servidor MK Auth

```bash
cd /tmp
git clone https://github.com/brsxdlols/mkauth-sicredi.git
cd mkauth-sicredi
sudo bash install.sh
```

Se o servidor não usa senha padrão do banco:

```bash
sudo MKAUTH_DB_PASS='sua_senha' bash install.sh
```

Variáveis aceitas:

- `MKAUTH_DIR` padrão `/opt/mk-auth`
- `MKAUTH_DB_HOST` padrão `127.0.0.1`
- `MKAUTH_DB_USER` padrão `root`
- `MKAUTH_DB_PASS` padrão `vertrigo`
- `MKAUTH_DB_NAME` padrão `mkradius`
- `INSTALL_CRON` padrão `1`

Exemplo sem instalar cron:

```bash
sudo INSTALL_CRON=0 bash install.sh
```

## Teste manual

Simular conciliação sem alterar nada:

```bash
/opt/php8/bin/php /opt/mk-auth/jobs/SICREDIAPI/conciliar_sicredi_desconto.php --days=15
```

Aplicar manualmente:

```bash
/opt/php8/bin/php /opt/mk-auth/jobs/SICREDIAPI/conciliar_sicredi_desconto.php --days=15 --apply
```

Log do cron:

```bash
tail -f /var/log/mk-auth/conciliar_sicredi_desconto.log
```

## Páginas instaladas

- `/admin/addons/rel_conciliacao_sicredi/`
- `/admin/addons/log_sicredi_api/`

Depois de instalar, use `Ctrl + F5` no navegador para limpar cache do menu/addons.

## Segurança operacional

O conciliador só baixa títulos quando:

- webhook Sicredi está em `sis_notificacoes` com `servico='sicredi'` e `resposta='REJEITADO'`;
- movimento é liquidação (`LIQUIDACAO_COMPE_H5`, `LIQUIDACAO_PIX`, `LIQUIDACAO_REDE`);
- `nossoNumero` e `idTituloEmpresa` apontam para o mesmo título no MK Auth;
- título ainda não está pago;
- valor liquidado confere com valor líquido do cadastro/plano ou valor original, com tolerância de centavos.

Antes de instalar, o script cria backups dos arquivos de menu quando eles forem alterados.
