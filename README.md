# mkauth-sicredi

Instalador dos addons e conciliador Sicredi para MK Auth.

## O que instala

- `Sicredi API - Baixas`: relatorio financeiro das baixas feitas pela API Sicredi, agrupado por dia.
- `Sicredi API - Logs`: tela web para troubleshooting dos webhooks recebidos em `sis_notificacoes`.
- Job `conciliar_sicredi_desconto.php`: concilia webhooks Sicredi `REJEITADO` quando o valor liquidado bate com valor liquido do cliente, considerando desconto/acrescimo, juros e multa.
- O job tambem cria o movimento em `sis_caixa` quando baixa o titulo e corrige retroativamente baixas `sicrediapi` sem caixa, sem duplicar historico ja existente.
- Cron a cada 5 minutos para executar a conciliacao.
- Entradas no menu Financeiro do MK Auth.

## Instalacao no servidor MK Auth

```bash
cd /tmp
git clone https://github.com/brsxdlols/mkauth-sicredi.git
cd mkauth-sicredi
sudo bash install.sh
```

Ou via bootstrap:

```bash
tmp=$(mktemp -d) && curl -fsSL https://github.com/brsxdlols/mkauth-sicredi/archive/refs/heads/main.tar.gz | tar -xz -C "$tmp" --strip-components=1 && sudo bash "$tmp/install.sh"; rm -rf "$tmp"
```

Se o servidor nao usa senha padrao do banco:

```bash
sudo MKAUTH_DB_PASS='sua_senha' bash install.sh
```

Variaveis aceitas:

- `MKAUTH_DIR` padrao `/opt/mk-auth`
- `MKAUTH_DB_HOST` padrao `127.0.0.1`
- `MKAUTH_DB_USER` padrao `root`
- `MKAUTH_DB_PASS` padrao `vertrigo`
- `MKAUTH_DB_NAME` padrao `mkradius`
- `INSTALL_CRON` padrao `1`
- `RUN_CONCILIADOR_NOW` padrao `1`

Exemplo sem instalar cron:

```bash
sudo INSTALL_CRON=0 bash install.sh
```

Exemplo instalando sem aplicar conciliacao imediatamente:

```bash
sudo RUN_CONCILIADOR_NOW=0 bash install.sh
```

## Teste manual

Simular conciliacao sem alterar nada:

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

## Paginas instaladas

- `/admin/addons/rel_conciliacao_sicredi/`
- `/admin/addons/log_sicredi_api/`

Depois de instalar, use `Ctrl + F5` no navegador para limpar cache do menu/addons.

## Seguranca operacional

O conciliador so baixa titulos quando:

- webhook Sicredi esta em `sis_notificacoes` com `servico='sicredi'` e `resposta='REJEITADO'`;
- movimento e liquidacao (`LIQUIDACAO_COMPE_H5`, `LIQUIDACAO_PIX`, `LIQUIDACAO_REDE`);
- `idTituloEmpresa` aponta para um unico `id_empresa` no MK Auth, ou, quando ausente, `nossoNumero` aponta para um unico titulo;
- titulo ainda nao esta pago, ou ja foi pago manualmente e nao veio de `arq.retorno`;
- valor liquidado/principal confere com valor liquido do cadastro/plano ou valor original, com tolerancia de centavos.

O lancamento em `sis_caixa` so e criado quando nao existe historico do mesmo titulo no caixa.

Antes de instalar, o script cria backups dos arquivos de menu quando eles forem alterados.
