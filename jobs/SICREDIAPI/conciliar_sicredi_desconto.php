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

function uuid4(): string
{
    $data = random_bytes(16);
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

function caixaHistorico(int $tituloId, string $login): string
{
    return "Recebimento do titulo {$tituloId} / {$login}";
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

$selectTitulosPorEmpresa = $mysqli->prepare(
    "SELECT l.id, l.login, l.nossonum, l.status, l.datapag, l.valor, l.valorpag,
            l.id_empresa, l.referencia, l.obs, l.coletor,
            c.nome, c.plano, c.desconto, c.acrescimo,
            p.valor AS valor_plano
       FROM sis_lanc l
       JOIN sis_cliente c ON c.login = l.login
  LEFT JOIN sis_plano p ON p.nome = c.plano
      WHERE l.id_empresa = ?
        AND l.deltitulo = 0
      ORDER BY l.id DESC"
);

$selectTitulosPorNosso = $mysqli->prepare(
    "SELECT l.id, l.login, l.nossonum, l.status, l.datapag, l.valor, l.valorpag,
            l.id_empresa, l.referencia, l.obs, l.coletor,
            c.nome, c.plano, c.desconto, c.acrescimo,
            p.valor AS valor_plano
       FROM sis_lanc l
       JOIN sis_cliente c ON c.login = l.login
  LEFT JOIN sis_plano p ON p.nome = c.plano
      WHERE l.nossonum = ?
        AND l.deltitulo = 0
      ORDER BY l.id DESC"
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
        SET resposta = ?
      WHERE id = ?
        AND resposta = 'REJEITADO'"
);


$selectCaixaTitulo = $mysqli->prepare(
    "SELECT id
       FROM sis_caixa
      WHERE historico LIKE CONCAT('%titulo ', ?, ' /%')
         OR historico LIKE CONCAT('%titulo ', ?, ' via%')
         OR historico LIKE CONCAT('%titulo ', ?, ' no arq.%')
      LIMIT 1"
);

$insertCaixa = $mysqli->prepare(
    "INSERT INTO sis_caixa
            (uuid_caixa, usuario, data, historico, complemento, entrada, saida, tipomov, planodecontas)
     VALUES (?, 'sicrediapi', ?, ?, '', ?, 0.00, 'aut', 'Outros')"
);

$selectCaixaPendentes = $mysqli->prepare(
    "SELECT l.id AS titulo_id, l.login, l.datapag, l.valorpag, l.coletor
       FROM sis_lanc l
      WHERE l.status = 'pago'
        AND l.datapag IS NOT NULL
        AND l.coletor = 'sicrediapi'
        AND l.datapag >= DATE_SUB(NOW(), INTERVAL ? DAY)
        AND l.deltitulo = 0
        AND NOT EXISTS (
            SELECT 1
              FROM sis_caixa cx
             WHERE cx.historico LIKE CONCAT('%titulo ', l.id, ' /%')
                OR cx.historico LIKE CONCAT('%titulo ', l.id, ' via%')
                OR cx.historico LIKE CONCAT('%titulo ', l.id, ' no arq.%')
        )
      ORDER BY l.datapag, l.id"
);

function caixaExiste(mysqli_stmt $stmt, int $tituloId): bool
{
    $stmt->bind_param('iii', $tituloId, $tituloId, $tituloId);
    $stmt->execute();
    return (bool) $stmt->get_result()->fetch_assoc();
}

function inserirCaixa(mysqli_stmt $selectStmt, mysqli_stmt $insertStmt, int $tituloId, string $login, string $data, float $valor): bool
{
    if (caixaExiste($selectStmt, $tituloId)) {
        return false;
    }
    $uuid = uuid4();
    $historico = caixaHistorico($tituloId, $login);
    $insertStmt->bind_param('sssd', $uuid, $data, $historico, $valor);
    $insertStmt->execute();
    if ($insertStmt->affected_rows !== 1) {
        throw new RuntimeException("caixa do titulo {$tituloId} nao inserido");
    }
    return true;
}

$candidatos = [];
$candidatosPorTitulo = [];
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
    if ($nosso === '' || (float) $valorLiquidacao <= 0) {
        $ignorados[] = "{$row['id']}: dados obrigatorios ausentes";
        continue;
    }

    if ($idEmpresa) {
        $selectTitulosPorEmpresa->bind_param('s', $idEmpresa);
        $selectTitulosPorEmpresa->execute();
        $resultTitulos = $selectTitulosPorEmpresa->get_result();
        $qtdTitulos = $resultTitulos->num_rows;
        if ($qtdTitulos !== 1) {
            $ignorados[] = "{$row['id']}: idTituloEmpresa=$idEmpresa encontrou $qtdTitulos titulo(s)";
            continue;
        }
        $titulo = $resultTitulos->fetch_assoc();
    } else {
        $selectTitulosPorNosso->bind_param('s', $nosso);
        $selectTitulosPorNosso->execute();
        $resultTitulos = $selectTitulosPorNosso->get_result();
        $qtdTitulos = $resultTitulos->num_rows;
        if ($qtdTitulos !== 1) {
            $ignorados[] = "{$row['id']}: idTituloEmpresa ausente e nosso=$nosso encontrou $qtdTitulos titulo(s) aberto(s)";
            continue;
        }
        $titulo = $resultTitulos->fetch_assoc();
    }

    $valorJuros = money2($dados['valorJuros'] ?? 0);
    $valorMulta = money2($dados['valorMulta'] ?? 0);
    $valorDescontoBanco = money2($dados['valorDesconto'] ?? 0);
    $valorAbatimento = money2($dados['valorAbatimento'] ?? 0);
    $valorPrincipalPago = money2(
        (float) $valorLiquidacao
        - (float) $valorJuros
        - (float) $valorMulta
        + (float) $valorDescontoBanco
        + (float) $valorAbatimento
    );
    $valorBase = money2($titulo['valor']);
    $valorLiquidoCadastro = money2((float) money2($titulo['valor']) - (float) money2($titulo['desconto']) + (float) money2($titulo['acrescimo']));
    $valorPlanoLiquido = money2((float) money2($titulo['valor_plano'] ?? $titulo['valor']) - (float) money2($titulo['desconto']) + (float) money2($titulo['acrescimo']));
    $valorAceito = (
        abs((float) $valorLiquidacao - (float) $valorLiquidoCadastro) <= 0.01 ||
        abs((float) $valorLiquidacao - (float) $valorPlanoLiquido) <= 0.01 ||
        abs((float) $valorLiquidacao - (float) $valorBase) <= 0.01 ||
        abs((float) $valorPrincipalPago - (float) $valorLiquidoCadastro) <= 0.01 ||
        abs((float) $valorPrincipalPago - (float) $valorPlanoLiquido) <= 0.01 ||
        abs((float) $valorPrincipalPago - (float) $valorBase) <= 0.01
    );
    if (!$valorAceito) {
        $ignorados[] = "{$row['id']}: valor nao confere titulo={$titulo['id']} pago=$valorLiquidacao principal=$valorPrincipalPago liquido=$valorLiquidoCadastro plano_liquido=$valorPlanoLiquido";
        continue;
    }

    $jaPago = ($titulo['status'] === 'pago' || !empty($titulo['datapag']));
    $coletor = strtolower(trim((string) ($titulo['coletor'] ?? '')));
    if ($jaPago && strpos($coletor, 'retorno') !== false) {
        $ignorados[] = "{$row['id']}: titulo {$titulo['id']} ja pago por arquivo de retorno";
        continue;
    }
    $respostaConciliada = $jaPago ? 'LIQUIDADO MANUAL CONCILIADO' : 'LIQUIDADO CONCILIADO';

    $tituloId = (int) $titulo['id'];
    if (isset($candidatosPorTitulo[$tituloId])) {
        $idx = $candidatosPorTitulo[$tituloId];
        $candidatos[$idx]['notif_ids'][] = (int) $row['id'];
        $ignorados[] = "{$row['id']}: webhook duplicado do titulo {$tituloId}, sera conciliado junto com #{$candidatos[$idx]['notif_id']}";
        continue;
    }

    $candidatosPorTitulo[$tituloId] = count($candidatos);
    $candidatos[] = [
        'notif_id' => (int) $row['id'],
        'notif_ids' => [(int) $row['id']],
        'titulo_id' => $tituloId,
        'login' => $titulo['login'],
        'nome' => $titulo['nome'],
        'nosso' => $nosso,
        'id_empresa' => $idEmpresa,
        'movimento' => $movimento,
        'valor_pago' => $valorLiquidacao,
        'valor_principal' => $valorPrincipalPago,
        'valor_lanc' => money2($titulo['valor']),
        'desconto' => money2($titulo['desconto']),
        'acrescimo' => money2($titulo['acrescimo']),
        'valor_liquido' => $valorLiquidoCadastro,
        'datapag' => eventDate($dados),
        'recibo' => (string) ($dados['idMovi'] ?? $dados['idEventoWebhook'] ?? ('sicredi-' . $row['id'])),
        'referencia' => $titulo['referencia'],
        'ja_pago' => $jaPago,
        'resposta' => $respostaConciliada,
        'caixa_historico' => caixaHistorico((int) $titulo['id'], (string) $titulo['login']),
    ];
}

$caixaPendentes = [];
$selectCaixaPendentes->bind_param('i', $days);
$selectCaixaPendentes->execute();
$resultCaixaPendentes = $selectCaixaPendentes->get_result();
while ($row = $resultCaixaPendentes->fetch_assoc()) {
    $caixaPendentes[] = [
        'titulo_id' => (int) $row['titulo_id'],
        'login' => (string) $row['login'],
        'datapag' => (string) $row['datapag'],
        'valor_pago' => money2($row['valorpag']),
        'historico' => caixaHistorico((int) $row['titulo_id'], (string) $row['login']),
    ];
}

echo ($apply ? "MODO APLICACAO\n" : "MODO SIMULACAO\n");
echo "Periodo analisado: {$days} dia(s)\n";
echo "Candidatos aprovados: " . count($candidatos) . "\n";
echo "Caixas pendentes: " . count($caixaPendentes) . "\n";
foreach ($candidatos as $c) {
    echo sprintf(
        "#%d titulo=%d login=%s nosso=%s pago=%s liquido=%s venc/ref=%s data=%s movimento=%s acao=%s\n",
        $c['notif_id'],
        $c['titulo_id'],
        $c['login'],
        $c['nosso'],
        $c['valor_pago'],
        $c['valor_liquido'],
        $c['referencia'],
        $c['datapag'],
        $c['movimento'],
        $c['ja_pago'] ? 'conciliar_manual' : 'baixar'
    );
}
foreach ($caixaPendentes as $c) {
    echo sprintf(
        "caixa_pendente titulo=%d login=%s valor=%s data=%s historico=%s\n",
        $c['titulo_id'],
        $c['login'],
        $c['valor_pago'],
        $c['datapag'],
        $c['historico']
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
        if (!$c['ja_pago']) {
            $valor = (float) $c['valor_pago'];
            $updateTitulo->bind_param('ssdi', $c['datapag'], $c['recibo'], $valor, $c['titulo_id']);
            $updateTitulo->execute();
            if ($updateTitulo->affected_rows !== 1) {
                throw new RuntimeException("titulo {$c['titulo_id']} nao atualizado");
            }
        }

        inserirCaixa($selectCaixaTitulo, $insertCaixa, $c['titulo_id'], $c['login'], $c['datapag'], (float) $c['valor_pago']);

        $resposta = $c['resposta'];
        foreach ($c['notif_ids'] as $notifId) {
            $updateNotificacao->bind_param('si', $resposta, $notifId);
            $updateNotificacao->execute();
            if ($updateNotificacao->affected_rows !== 1) {
                throw new RuntimeException("notificacao {$notifId} nao atualizada");
            }
        }
    }
    foreach ($caixaPendentes as $c) {
        inserirCaixa($selectCaixaTitulo, $insertCaixa, $c['titulo_id'], $c['login'], $c['datapag'], (float) $c['valor_pago']);
    }

    $mysqli->commit();
} catch (Throwable $e) {
    $mysqli->rollback();
    fwrite(STDERR, "ROLLBACK: {$e->getMessage()}\n");
    exit(3);
}

echo "Aplicado com sucesso: " . count($candidatos) . " baixa(s), " . count($caixaPendentes) . " caixa(s) corrigido(s).\n";
