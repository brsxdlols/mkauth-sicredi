#!/usr/bin/env /opt/php8/bin/php
<?php
declare(strict_types=1);

const DB_HOST = '127.0.0.1';
const DB_USER = 'root';
const DB_PASS = 'vertrigo';
const DB_NAME = 'mkradius';

$apply = in_array('--apply', $argv, true);
$days = 30;
foreach ($argv as $arg) {
    if (preg_match('/^--days=(\d+)$/', $arg, $m)) {
        $days = max(1, (int) $m[1]);
    }
}

$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($mysqli->connect_error) {
    fwrite(STDERR, "Erro MySQL: {$mysqli->connect_error}\n");
    exit(2);
}
$mysqli->set_charset('utf8');

function money2($value): string
{
    return number_format((float) str_replace(',', '.', (string) $value), 2, '.', '');
}

function eventDate(array $dados): string
{
    $raw = $dados['dataEvento'] ?? $dados['dataPrevisaoPagamento'] ?? null;
    if (is_array($raw) && count($raw) >= 3) {
        $h = (int) ($raw[3] ?? 0);
        $i = (int) ($raw[4] ?? 0);
        $s = (int) ($raw[5] ?? 0);
        return sprintf('%04d-%02d-%02d %02d:%02d:%02d', $raw[0], $raw[1], $raw[2], $h, $i, $s);
    }
    if (is_string($raw) && $raw !== '') {
        $ts = strtotime($raw);
        if ($ts !== false) {
            return date('Y-m-d H:i:s', $ts);
        }
    }
    return date('Y-m-d H:i:s');
}

function parseTituloEmpresa(?string $idTituloEmpresa): ?string
{
    if (!$idTituloEmpresa) {
        return null;
    }
    return preg_match('/MKAUTH(\d+)/', $idTituloEmpresa, $m) ? $m[1] : null;
}

$selectNotificacoes = $mysqli->prepare(
    "SELECT id, data, dados
       FROM sis_notificacoes
      WHERE servico = 'sicredi'
        AND resposta = 'REJEITADO'
        AND data >= DATE_SUB(NOW(), INTERVAL ? DAY)
      ORDER BY id"
);
$selectNotificacoes->bind_param('i', $days);
$selectNotificacoes->execute();
$notificacoes = $selectNotificacoes->get_result();

$selectTitulo = $mysqli->prepare(
    "SELECT l.id, l.login, l.nossonum, l.status, l.datapag, l.valor, l.valorpag,
            l.id_empresa, l.referencia, l.obs,
            c.nome, c.plano, c.desconto, c.acrescimo,
            p.valor AS valor_plano
       FROM sis_lanc l
       JOIN sis_cliente c ON c.login = l.login
  LEFT JOIN sis_plano p ON p.nome = c.plano
      WHERE l.nossonum = ?
        AND l.id_empresa = ?
        AND l.deltitulo = 0
      LIMIT 1"
);

$updateTitulo = $mysqli->prepare(
    "UPDATE sis_lanc
        SET datapag = ?,
            recibo = ?,
            status = 'pago',
            coletor = 'sicrediapi',
            valorpag = ?,
            formapag = 'boleto',
            num_recibos = 1,
            tarifa_paga = 0.00,
            deltitulo = 0,
            datadel = NULL,
            num_retornos = IF(num_retornos = 0, 1, num_retornos),
            oco06 = 1
      WHERE id = ?
        AND status <> 'pago'
        AND datapag IS NULL"
);

$updateNotificacao = $mysqli->prepare(
    "UPDATE sis_notificacoes
        SET resposta = 'LIQUIDADO CONCILIADO'
      WHERE id = ?
        AND resposta = 'REJEITADO'"
);

$candidatos = [];
$ignorados = [];

while ($row = $notificacoes->fetch_assoc()) {
    $dados = json_decode((string) $row['dados'], true);
    if (!is_array($dados)) {
        $ignorados[] = "{$row['id']}: JSON invalido";
        continue;
    }

    $movimento = (string) ($dados['movimento'] ?? '');
    if (!in_array($movimento, ['LIQUIDACAO_COMPE_H5', 'LIQUIDACAO_PIX', 'LIQUIDACAO_REDE'], true)) {
        $ignorados[] = "{$row['id']}: movimento nao liquidacao ($movimento)";
        continue;
    }

    $nosso = (string) ($dados['nossoNumero'] ?? '');
    $idEmpresa = parseTituloEmpresa((string) ($dados['idTituloEmpresa'] ?? ''));
    $valorLiquidacao = money2($dados['valorLiquidacao'] ?? '');
    if ($nosso === '' || !$idEmpresa || (float) $valorLiquidacao <= 0) {
        $ignorados[] = "{$row['id']}: dados obrigatorios ausentes";
        continue;
    }

    $selectTitulo->bind_param('ss', $nosso, $idEmpresa);
    $selectTitulo->execute();
    $titulo = $selectTitulo->get_result()->fetch_assoc();
    if (!$titulo) {
        $ignorados[] = "{$row['id']}: titulo nao encontrado para nosso=$nosso id_empresa=$idEmpresa";
        continue;
    }
    if ($titulo['status'] === 'pago' || !empty($titulo['datapag'])) {
        $ignorados[] = "{$row['id']}: titulo {$titulo['id']} ja pago";
        continue;
    }

    $valorBase = money2($titulo['valor']);
    $valorLiquidoCadastro = money2((float) money2($titulo['valor']) - (float) money2($titulo['desconto']) + (float) money2($titulo['acrescimo']));
    $valorPlanoLiquido = money2((float) money2($titulo['valor_plano'] ?? $titulo['valor']) - (float) money2($titulo['desconto']) + (float) money2($titulo['acrescimo']));
    $valorAceito = (
        abs((float) $valorLiquidacao - (float) $valorLiquidoCadastro) <= 0.01 ||
        abs((float) $valorLiquidacao - (float) $valorPlanoLiquido) <= 0.01 ||
        abs((float) $valorLiquidacao - (float) $valorBase) <= 0.01
    );
    if (!$valorAceito) {
        $ignorados[] = "{$row['id']}: valor nao confere titulo={$titulo['id']} pago=$valorLiquidacao liquido=$valorLiquidoCadastro plano_liquido=$valorPlanoLiquido";
        continue;
    }

    $candidatos[] = [
        'notif_id' => (int) $row['id'],
        'titulo_id' => (int) $titulo['id'],
        'login' => $titulo['login'],
        'nome' => $titulo['nome'],
        'nosso' => $nosso,
        'id_empresa' => $idEmpresa,
        'movimento' => $movimento,
        'valor_pago' => $valorLiquidacao,
        'valor_lanc' => money2($titulo['valor']),
        'desconto' => money2($titulo['desconto']),
        'acrescimo' => money2($titulo['acrescimo']),
        'valor_liquido' => $valorLiquidoCadastro,
        'datapag' => eventDate($dados),
        'recibo' => (string) ($dados['idMovi'] ?? $dados['idEventoWebhook'] ?? ('sicredi-' . $row['id'])),
        'referencia' => $titulo['referencia'],
    ];
}

echo ($apply ? "MODO APLICACAO\n" : "MODO SIMULACAO\n");
echo "Periodo analisado: {$days} dia(s)\n";
echo "Candidatos aprovados: " . count($candidatos) . "\n";
foreach ($candidatos as $c) {
    echo sprintf(
        "#%d titulo=%d login=%s nosso=%s pago=%s liquido=%s venc/ref=%s data=%s movimento=%s\n",
        $c['notif_id'],
        $c['titulo_id'],
        $c['login'],
        $c['nosso'],
        $c['valor_pago'],
        $c['valor_liquido'],
        $c['referencia'],
        $c['datapag'],
        $c['movimento']
    );
}

if (!$apply) {
    echo "Nada alterado. Use --apply para aplicar.\n";
    if ($ignorados) {
        echo "Ignorados: " . count($ignorados) . "\n";
        foreach (array_slice($ignorados, 0, 20) as $motivo) {
            echo "- $motivo\n";
        }
    }
    exit(0);
}

$mysqli->begin_transaction();
try {
    foreach ($candidatos as $c) {
        $valor = (float) $c['valor_pago'];
        $updateTitulo->bind_param('ssdi', $c['datapag'], $c['recibo'], $valor, $c['titulo_id']);
        $updateTitulo->execute();
        if ($updateTitulo->affected_rows !== 1) {
            throw new RuntimeException("titulo {$c['titulo_id']} nao atualizado");
        }

        $notifId = $c['notif_id'];
        $updateNotificacao->bind_param('i', $notifId);
        $updateNotificacao->execute();
        if ($updateNotificacao->affected_rows !== 1) {
            throw new RuntimeException("notificacao {$notifId} nao atualizada");
        }
    }
    $mysqli->commit();
} catch (Throwable $e) {
    $mysqli->rollback();
    fwrite(STDERR, "ROLLBACK: {$e->getMessage()}\n");
    exit(3);
}

echo "Aplicado com sucesso: " . count($candidatos) . " baixa(s).\n";
