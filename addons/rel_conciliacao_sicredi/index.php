<?php require_once('config.php'); ?>
<!DOCTYPE html>
<html lang="pt-BR" class="has-navbar-fixed-top">
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta charset="utf-8">
    <title>MK-AUTH :: <?php echo h($Manifest->name); ?></title>
    <link href="../../estilos/mk-auth.css" rel="stylesheet" type="text/css" />
    <link href="../../estilos/font-awesome.css" rel="stylesheet" type="text/css" />
    <link href="../../estilos/bi-icons.css" rel="stylesheet" type="text/css" />
    <script src="../../scripts/jquery.js"></script>
    <script src="../../scripts/mk-auth.js"></script>
    <style>
        * { box-sizing: border-box; }
        :root { --sicredi-green: #1f8f3a; --sicredi-dark: #11582b; --sicredi-soft: #edf8ef; --sicredi-border: #cfe8d3; --sicredi-lime: #75b82a; }
        body { background: #f5f8f4; color: #243241; overflow-x: hidden; }
        .wrap { width: 100%; max-width: 100%; margin: 18px auto; padding: 0 14px 28px; }
        .panel { width: 100%; max-width: 100%; background: #fff; border: 1px solid var(--sicredi-border); border-radius: 6px; padding: 14px; margin-bottom: 12px; }
        .titlebar { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; }
        .title-copy { display: flex; align-items: center; gap: 14px; min-width: 260px; }
        .sicredi-logo { width: 118px; max-width: 34vw; height: auto; display: block; }
        .titlebar h1 { color: var(--sicredi-dark); font-size: 22px; font-weight: 700; margin: 0; }
        .muted { color: #6b7b89; font-size: 12px; }
        .client-link { color: #063; text-decoration: none; }
        .client-link:hover { text-decoration: underline; }
        .filters { display: grid; grid-template-columns: 150px 150px 170px minmax(220px, 1fr) 170px 92px; gap: 10px; align-items: end; }
        .filter-action { align-self: start; display: flex; align-items: flex-start; padding-top: 23px; }
        .filter-action .btn { width: 100%; margin: 0; }
        .field label { display: block; font-size: 12px; font-weight: 700; margin-bottom: 4px; color: #314457; }
        .field input, .field select { width: 100%; height: 36px; border: 1px solid #b9d8bf; border-radius: 4px; padding: 6px 8px; background: #fff; color: #1d2d3a; }
        .field input:focus, .field select:focus { border-color: var(--sicredi-green); box-shadow: 0 0 0 2px rgba(31, 143, 58, .12); outline: 0; }
        .btn { height: 36px; border: 0; border-radius: 4px; padding: 0 14px; cursor: pointer; font-weight: 700; }
        .btn-primary { background: var(--sicredi-green); color: #fff; }
        .btn-light { display: inline-flex; align-items: center; background: var(--sicredi-soft); color: var(--sicredi-dark); text-decoration: none; border: 1px solid var(--sicredi-border); }
        .summary { display: grid; grid-template-columns: repeat(5, minmax(150px, 1fr)); gap: 10px; }
        .metric { border: 1px solid var(--sicredi-border); border-radius: 6px; padding: 12px; background: #fbfefb; }
        .metric b { display: block; font-size: 21px; color: var(--sicredi-dark); line-height: 1.25; }
        .metric span { color: #59735d; font-size: 11px; text-transform: uppercase; font-weight: 700; }
        table { width: 100%; table-layout: fixed; border-collapse: separate; border-spacing: 0; background: #fff; }
        thead { position: sticky; top: 0; z-index: 20; }
        th { background: var(--sicredi-green); color: #fff; font-size: 11px; text-align: left; padding: 8px 7px; position: sticky; top: 0; z-index: 21; box-shadow: 0 1px 0 var(--sicredi-dark); }
        td { border-bottom: 1px solid #e6edf3; padding: 7px; font-size: 12px; vertical-align: top; overflow-wrap: anywhere; }
        th:nth-child(1), td:nth-child(1) { width: 72px; }
        th:nth-child(2), td:nth-child(2) { width: 48px; }
        th:nth-child(3), td:nth-child(3) { width: 130px; }
        th:nth-child(4), td:nth-child(4) { width: 76px; }
        th:nth-child(5), td:nth-child(5) { width: 150px; }
        th:nth-child(6), td:nth-child(6) { width: 82px; }
        th:nth-child(7), td:nth-child(7) { width: 64px; }
        th:nth-child(8), td:nth-child(8) { width: 78px; }
        th:nth-child(9), td:nth-child(9) { width: 138px; }
        th:nth-child(10), td:nth-child(10) { width: 78px; }
        th:nth-child(11), td:nth-child(11) { width: 80px; }
        tr:nth-child(even) td { background: #f8fbfd; }
        .date-row td { background: #dff2e2 !important; color: var(--sicredi-dark); font-weight: 700; font-size: 14px; border-top: 8px solid #fff; }
        .total-row td { background: #f1faf2 !important; color: var(--sicredi-dark); font-weight: 700; font-size: 13px; border-top: 1px solid var(--sicredi-border); border-bottom: 2px solid var(--sicredi-border); }
        .num { text-align: right; white-space: normal; }
        .badge { display: inline-block; padding: 3px 7px; border-radius: 4px; font-weight: 700; font-size: 12px; background: #e7f6ee; color: #17643b; }
        .badge-soft { background: #edf3f8; color: #405263; }
        .badge-warn { background: #fff4df; color: #855800; }
        .scroll { overflow-y: auto; overflow-x: hidden; max-height: 68vh; border: 1px solid #dfe5ec; border-radius: 6px; }
        @media (max-width: 1050px) {
            .filters, .summary { grid-template-columns: 1fr; }
            .filter-action { padding-top: 0; }
            .scroll { max-height: none; }
        }
        @media (max-width: 760px) {
            .wrap { margin: 10px 0; padding: 0 8px 20px; }
            .panel { padding: 12px; }
            .title-copy { width: 100%; align-items: flex-start; }
            .sicredi-logo { width: 96px; }
            .titlebar h1 { font-size: 20px; }
            .btn, .btn-light, .btn-primary { width: 100%; justify-content: center; margin-top: 6px; }
            .summary { gap: 8px; }
            .metric { padding: 10px; }
            .metric b { font-size: 18px; }
            .scroll { overflow: visible; border: 0; }
            table, thead, tbody, tr, td { display: block; width: 100% !important; }
            thead { display: none; }
            tr { margin-bottom: 10px; border: 1px solid #dfe8f0; border-radius: 6px; background: #fff; overflow: hidden; }
            td { display: flex; justify-content: space-between; gap: 12px; border-bottom: 1px solid #edf2f6; font-size: 12px; text-align: right; }
            td::before { content: attr(data-label); flex: 0 0 94px; color: #607386; font-weight: 700; text-align: left; }
            td:last-child { border-bottom: 0; }
            .date-row, .total-row { border: 0; margin: 14px 0 8px; }
            .date-row td, .total-row td { display: block; text-align: left; border-radius: 6px; border: 0; }
            .date-row td::before, .total-row td::before { content: ''; display: none; }
            .num { text-align: right; }
        }
        @media print {
            .no-print, #systopo, .navbar { display: none !important; }
            body { background: #fff; }
            .wrap { max-width: none; margin: 0; padding: 0; }
            .panel, .scroll { border: 0; }
            th { position: static; }
        }
    </style>
</head>
<body>
<?php include('../../topo.php'); ?>
<?php
$today = date('Y-m-d');
$start = $_GET['data_inicial'] ?? date('Y-m-01');
$end = $_GET['data_final'] ?? $today;
$origin = $_GET['origem'] ?? 'todas';
$search = trim($_GET['busca'] ?? '');
$order = $_GET['ordem'] ?? 'data_desc';

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start)) $start = date('Y-m-01');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) $end = $today;
if (!in_array($origin, ['todas', 'conciliadas', 'nativas', 'manuais'], true)) $origin = 'todas';

function sicredi_payload_value($dados, $field)
{
    $payload = json_decode((string) $dados, true);
    if (is_array($payload) && isset($payload[$field])) {
        return is_scalar($payload[$field]) ? (string) $payload[$field] : '';
    }

    if (preg_match('/"' . preg_quote($field, '/') . '"\s*:\s*"?([^",}\]]+)/', (string) $dados, $m)) {
        return trim($m[1]);
    }

    return '';
}

function sicredi_titulo_empresa_id($dados)
{
    $idTituloEmpresa = sicredi_payload_value($dados, 'idTituloEmpresa');
    return preg_match('/MKAUTH(\d+)/', $idTituloEmpresa, $m) ? $m[1] : '';
}

function sicredi_nosso_numero($dados)
{
    return preg_replace('/\D+/', '', sicredi_payload_value($dados, 'nossoNumero'));
}

function sql_placeholders($count)
{
    return implode(',', array_fill(0, $count, '?'));
}

$where = "servico = 'sicredi'
          AND data BETWEEN ? AND ?
          AND (
              resposta LIKE 'LIQUIDADO%'
              OR resposta = 'BAIXADO'
              OR resposta = 'LIQUIDADO CONCILIADO'
              OR resposta LIKE 'BAIXA MANUAL%'
          )";
$params = [$start . ' 00:00:00', $end . ' 23:59:59'];
$types = 'ss';

if ($origin === 'conciliadas') {
    $where .= " AND resposta LIKE '%CONCILIADO%'";
} elseif ($origin === 'nativas') {
    $where .= " AND resposta NOT LIKE '%CONCILIADO%' AND resposta NOT LIKE 'BAIXA MANUAL%'";
} elseif ($origin === 'manuais') {
    $where .= " AND resposta LIKE 'BAIXA MANUAL%'";
}

$sql = "SELECT id, data, dados, resposta
        FROM sis_notificacoes
        WHERE $where
        ORDER BY data DESC, id DESC";

$stmt = mysqli_prepare($link, $sql);
mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$eventsByEmpresa = [];
$eventsByNosso = [];
while ($event = mysqli_fetch_assoc($result)) {
    $event['id_empresa_match'] = sicredi_titulo_empresa_id($event['dados']);
    $event['nosso_match'] = sicredi_nosso_numero($event['dados']);

    if ($event['id_empresa_match'] !== '') {
        $eventsByEmpresa[$event['id_empresa_match']][] = $event;
    }

    if ($event['nosso_match'] !== '') {
        $eventsByNosso[$event['nosso_match']][] = $event;
    }
}

$rows = [];
$total = 0.0;
$clientes = [];
$dias = [];
$conciliadas = 0;

$idEmpresas = array_keys($eventsByEmpresa);
$nossos = array_keys($eventsByNosso);

if ($idEmpresas || $nossos) {
    $titleWhere = ["l.status = 'pago'", "LOWER(IFNULL(l.coletor, '')) NOT LIKE '%retorno%'"];
    $titleParams = [];
    $titleTypes = '';
    $matchParts = [];

    if ($idEmpresas) {
        $matchParts[] = 'l.id_empresa IN (' . sql_placeholders(count($idEmpresas)) . ')';
        foreach ($idEmpresas as $idEmpresa) {
            $titleParams[] = $idEmpresa;
            $titleTypes .= 's';
        }
    }

    if ($nossos) {
        $matchParts[] = 'l.nossonum IN (' . sql_placeholders(count($nossos)) . ')';
        foreach ($nossos as $nosso) {
            $titleParams[] = $nosso;
            $titleTypes .= 's';
        }
    }

    $titleWhere[] = '(' . implode(' OR ', $matchParts) . ')';
    $titleSql = "SELECT
                    l.id AS titulo,
                    l.login,
                    c.nome,
                    c.uuid_cliente,
                    l.nossonum,
                    l.id_empresa,
                    l.datavenc,
                    l.processamento,
                    l.datapag,
                    l.valor,
                    l.valorpag,
                    c.desconto,
                    c.acrescimo,
                    l.recibo,
                    l.referencia,
                    l.obs,
                    l.formapag,
                    l.coletor
                FROM sis_lanc l
                LEFT JOIN sis_cliente c ON c.login = l.login
                WHERE " . implode(' AND ', $titleWhere);
    $titleStmt = mysqli_prepare($link, $titleSql);
    mysqli_stmt_bind_param($titleStmt, $titleTypes, ...$titleParams);
    mysqli_stmt_execute($titleStmt);
    $titleResult = mysqli_stmt_get_result($titleStmt);

    $searchNeedle = $search !== '' ? strtolower($search) : '';
    while ($title = mysqli_fetch_assoc($titleResult)) {
        $matchedEvents = [];
        if ((string) $title['id_empresa'] !== '' && isset($eventsByEmpresa[(string) $title['id_empresa']])) {
            $matchedEvents = array_merge($matchedEvents, $eventsByEmpresa[(string) $title['id_empresa']]);
        }
        if ((string) $title['nossonum'] !== '' && isset($eventsByNosso[(string) $title['nossonum']])) {
            $matchedEvents = array_merge($matchedEvents, $eventsByNosso[(string) $title['nossonum']]);
        }

        $uniqueEvents = [];
        foreach ($matchedEvents as $event) {
            $uniqueEvents[(int) $event['id']] = $event;
        }
        if (!$uniqueEvents) {
            continue;
        }

        if ($searchNeedle !== '') {
            $haystack = strtolower(implode(' ', [
                $title['titulo'],
                $title['login'],
                $title['nome'],
                $title['nossonum'],
                $title['recibo'],
                $title['id_empresa'],
            ]));
            if (strpos($haystack, $searchNeedle) === false) {
                continue;
            }
        }

        $maxId = 0;
        $maxData = '';
        $respostas = [];
        foreach ($uniqueEvents as $event) {
            if ((int) $event['id'] > $maxId) {
                $maxId = (int) $event['id'];
            }
            if ($maxData === '' || strcmp((string) $event['data'], $maxData) > 0) {
                $maxData = (string) $event['data'];
            }
            $respostas[(string) $event['resposta']] = true;
        }

        $row = $title;
        $row['notificacao_id'] = $maxId;
        $row['data_notificacao'] = $maxData;
        $row['data_relatorio'] = $maxData;
        $row['respostas_api'] = implode(', ', array_keys($respostas));
        $rows[] = $row;
    }

    usort($rows, function ($a, $b) use ($order) {
        $cmp = strcmp((string) $a['data_relatorio'], (string) $b['data_relatorio']);
        if ($cmp === 0) {
            $cmp = ((int) $a['titulo']) <=> ((int) $b['titulo']);
        }
        return $order === 'data_asc' ? $cmp : -$cmp;
    });

    foreach ($rows as $row) {
        $total += (float) $row['valorpag'];
        $clientes[$row['login']] = true;
        $dias[substr($row['data_relatorio'], 0, 10)] = true;
        if (strpos((string) $row['respostas_api'], 'CONCILIADO') !== false) {
            $conciliadas++;
        }
    }
}
?>
<div class="wrap">
    <div class="panel titlebar">
        <div class="title-copy">
            <img class="sicredi-logo" src="sicredi-logo.svg" alt="Sicredi">
            <div>
                <h1>Baixas Sicredi API</h1>
                <div class="muted">Relatorio por data das baixas recebidas e enviadas pela integracao Sicredi, incluindo conciliacao de desconto.</div>
            </div>
        </div>
        <div class="no-print">
            <a class="btn btn-light" href="/admin/addons/log_sicredi_api/">Logs de webhook</a>
            <a class="btn btn-light" href="/admin/addons/rel_conciliacao_sicredi/index.php">Atualizar</a>
            <button class="btn btn-primary" onclick="window.print()">Imprimir</button>
        </div>
    </div>

    <form class="panel filters no-print" method="get">
        <div class="field">
            <label>Data inicial</label>
            <input type="date" name="data_inicial" value="<?php echo h($start); ?>">
        </div>
        <div class="field">
            <label>Data final</label>
            <input type="date" name="data_final" value="<?php echo h($end); ?>">
        </div>
        <div class="field">
            <label>Tipo de baixa</label>
            <select name="origem">
                <option value="todas" <?php echo $origin === 'todas' ? 'selected' : ''; ?>>Todas da API</option>
                <option value="conciliadas" <?php echo $origin === 'conciliadas' ? 'selected' : ''; ?>>Conciliadas</option>
                <option value="nativas" <?php echo $origin === 'nativas' ? 'selected' : ''; ?>>Nativas Sicredi</option>
                <option value="manuais" <?php echo $origin === 'manuais' ? 'selected' : ''; ?>>Manuais enviadas</option>
            </select>
        </div>
        <div class="field">
            <label>Busca</label>
            <input type="text" name="busca" value="<?php echo h($search); ?>" placeholder="Cliente, login, titulo, NN">
        </div>
        <div class="field">
            <label>Ordem</label>
            <select name="ordem">
                <option value="data_desc" <?php echo $order !== 'data_asc' ? 'selected' : ''; ?>>Recentes primeiro</option>
                <option value="data_asc" <?php echo $order === 'data_asc' ? 'selected' : ''; ?>>Antigos primeiro</option>
            </select>
        </div>
        <div class="filter-action">
            <button class="btn btn-primary" type="submit">Filtrar</button>
        </div>
    </form>

    <div class="panel summary">
        <div class="metric"><span>Baixas da API</span><b><?php echo count($rows); ?></b></div>
        <div class="metric"><span>Conciliadas</span><b><?php echo $conciliadas; ?></b></div>
        <div class="metric"><span>Clientes</span><b><?php echo count($clientes); ?></b></div>
        <div class="metric"><span>Dias com baixa</span><b><?php echo count($dias); ?></b></div>
        <div class="metric"><span>Total baixado</span><b><?php echo money_br($total); ?></b></div>
    </div>

    <div class="panel">
        <div class="scroll">
            <table>
                <thead>
                <tr>
                    <th>Data baixa</th>
                    <th>Titulo</th>
                    <th>Cliente</th>
                    <th>Nosso No.</th>
                    <th>Referencia</th>
                    <th class="num">Valor titulo</th>
                    <th class="num">Desc.</th>
                    <th class="num">Valor pago</th>
                    <th>Tipo API</th>
                    <th>Recibo</th>
                    <th>Notificacao</th>
                </tr>
                </thead>
                <tbody>
                <?php
                if (!$rows) {
                    echo '<tr><td colspan="11">Nenhuma baixa Sicredi API no periodo informado.</td></tr>';
                }
                $currentDay = null;
                $dayTotal = 0.0;
                $dayCount = 0;
                foreach ($rows as $row) {
                    $day = substr($row['data_relatorio'], 0, 10);
                    if ($currentDay !== $day) {
                        if ($currentDay !== null) {
                            echo '<tr class="total-row"><td colspan="7">Total do dia ' . h(date_br($currentDay)) . '</td><td class="num">' . h(money_br($dayTotal)) . '</td><td colspan="3">' . $dayCount . ' baixa(s)</td></tr>';
                        }
                        $currentDay = $day;
                        $dayTotal = 0.0;
                        $dayCount = 0;
                        echo '<tr class="date-row"><td colspan="11">' . h(date_br($day)) . '</td></tr>';
                    }

                    $dayTotal += (float) $row['valorpag'];
                    $dayCount++;
                    $apiType = (string) $row['respostas_api'];
                    $badgeClass = strpos($apiType, 'CONCILIADO') !== false ? 'badge badge-warn' : 'badge badge-soft';

                    echo '<tr>';
                    echo '<td data-label="Data">' . h(date_br($row['data_relatorio'], true)) . '</td>';
                    echo '<td data-label="Titulo"><a href="/admin/titulo_info.hhvm?titulo=' . h($row['titulo']) . '" target="_blank">' . h($row['titulo']) . '</a></td>';
                    $clienteNome = h($row['nome']);
                    if (!empty($row['uuid_cliente'])) {
                        $clienteNome = '<a class="client-link" href="/admin/cliente_det.hhvm?uuid=' . h($row['uuid_cliente']) . '" target="_blank"><b>' . h($row['nome']) . '</b></a>';
                    } else {
                        $clienteNome = '<b>' . h($row['nome']) . '</b>';
                    }
                    echo '<td data-label="Cliente">' . $clienteNome . '<br><span class="muted">' . h($row['login']) . '</span></td>';
                    echo '<td data-label="Nosso No.">' . h($row['nossonum']) . '</td>';
                    echo '<td data-label="Referencia">' . h($row['referencia']) . '<br><span class="muted">' . h($row['obs']) . '</span><br><span class="muted"><b>Venc.</b> ' . h(date_br($row['datavenc'])) . ' &nbsp; <b>Lan&ccedil;:</b> ' . h(date_br($row['processamento'])) . '</span></td>';
                    echo '<td data-label="Valor titulo" class="num">' . h(money_br($row['valor'])) . '</td>';
                    echo '<td data-label="Desc." class="num">' . h(money_br($row['desconto'])) . '</td>';
                    echo '<td data-label="Valor pago" class="num"><span class="badge">' . h(money_br($row['valorpag'])) . '</span></td>';
                    echo '<td data-label="Tipo API"><span class="' . h($badgeClass) . '">' . h($apiType) . '</span></td>';
                    echo '<td data-label="Recibo">' . h($row['recibo']) . '<br><span class="muted">' . h($row['coletor']) . '</span></td>';
                    echo '<td data-label="Notificacao"><a href="/admin/notificacao.hhvm?id=' . h($row['notificacao_id']) . '" target="_blank">#' . h($row['notificacao_id']) . '</a><br><span class="muted">' . h(date_br($row['data_notificacao'], true)) . '</span></td>';
                    echo '</tr>';
                }
                if ($currentDay !== null) {
                    echo '<tr class="total-row"><td colspan="7">Total do dia ' . h(date_br($currentDay)) . '</td><td class="num">' . h(money_br($dayTotal)) . '</td><td colspan="3">' . $dayCount . ' baixa(s)</td></tr>';
                }
                ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php include('../../baixo.php'); ?>
<script src="../../menu.js.hhvm"></script>
</body>
</html>
