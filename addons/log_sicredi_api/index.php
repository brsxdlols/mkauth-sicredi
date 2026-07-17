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
        .panel { width: 100%; background: #fff; border: 1px solid var(--sicredi-border); border-radius: 6px; padding: 14px; margin-bottom: 12px; }
        .titlebar { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; }
        .title-copy { display: flex; align-items: center; gap: 14px; min-width: 260px; }
        .sicredi-logo { width: 118px; max-width: 34vw; height: auto; display: block; }
        .titlebar h1 { color: var(--sicredi-dark); font-size: 22px; font-weight: 700; margin: 0; }
        .muted { color: #6b7b89; font-size: 12px; }
        .filters { display: grid; grid-template-columns: 150px 150px 170px minmax(220px, 1fr) 95px; gap: 10px; align-items: end; }
        .filter-action { align-self: start; display: flex; align-items: flex-start; padding-top: 23px; }
        .filter-action .btn { width: 100%; margin: 0; }
        .field label { display: block; font-size: 12px; font-weight: 700; margin-bottom: 4px; color: #314457; }
        .field input, .field select { width: 100%; height: 36px; border: 1px solid #b9d8bf; border-radius: 4px; padding: 6px 8px; background: #fff; color: #1d2d3a; }
        .field input:focus, .field select:focus { border-color: var(--sicredi-green); box-shadow: 0 0 0 2px rgba(31, 143, 58, .12); outline: 0; }
        .btn { height: 36px; border: 0; border-radius: 4px; padding: 0 14px; cursor: pointer; font-weight: 700; }
        .btn-primary { background: var(--sicredi-green); color: #fff; }
        .btn-light { display: inline-flex; align-items: center; background: var(--sicredi-soft); color: var(--sicredi-dark); text-decoration: none; border: 1px solid var(--sicredi-border); }
        .summary { display: grid; grid-template-columns: repeat(4, minmax(150px, 1fr)); gap: 10px; }
        .metric { border: 1px solid var(--sicredi-border); border-radius: 6px; padding: 12px; background: #fbfefb; }
        .metric b { display: block; font-size: 21px; color: var(--sicredi-dark); line-height: 1.25; }
        .metric span { color: #59735d; font-size: 11px; text-transform: uppercase; font-weight: 700; }
        table { width: 100%; table-layout: fixed; border-collapse: separate; border-spacing: 0; background: #fff; }
        thead { position: sticky; top: 0; z-index: 20; }
        th { background: var(--sicredi-green); color: #fff; font-size: 11px; text-align: left; padding: 8px 7px; position: sticky; top: 0; z-index: 21; box-shadow: 0 1px 0 var(--sicredi-dark); }
        td { border-bottom: 1px solid #e6edf3; padding: 8px 7px; font-size: 12px; vertical-align: top; overflow-wrap: anywhere; }
        th:nth-child(1), td:nth-child(1) { width: 72px; }
        th:nth-child(2), td:nth-child(2) { width: 130px; }
        th:nth-child(3), td:nth-child(3) { width: 105px; }
        th:nth-child(4), td:nth-child(4) { width: 105px; }
        th:nth-child(5), td:nth-child(5) { width: 90px; }
        th:nth-child(6), td:nth-child(6) { width: 155px; }
        th:nth-child(7), td:nth-child(7) { width: 120px; }
        tr:nth-child(even) td { background: #f8fbfd; }
        .scroll { overflow-y: auto; overflow-x: hidden; max-height: 70vh; border: 1px solid #dfe5ec; border-radius: 6px; }
        .badge { display: inline-block; padding: 3px 7px; border-radius: 4px; font-weight: 700; font-size: 12px; background: #edf3f8; color: #405263; }
        .ok { background: #e7f6ee; color: #17643b; }
        .warn { background: #fff4df; color: #855800; }
        .err { background: #fdecec; color: #a12b2b; }
        details { margin-top: 6px; }
        summary { cursor: pointer; color: #236c9d; font-weight: 700; }
        pre { white-space: pre-wrap; word-break: break-word; margin: 8px 0 0; padding: 10px; border: 1px solid #dce5ed; border-radius: 4px; background: #f9fbfd; color: #263846; max-height: 280px; overflow: auto; }
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
            .scroll { overflow: visible; border: 0; }
            table, thead, tbody, tr, td { display: block; width: 100% !important; }
            thead { display: none; }
            tr { margin-bottom: 10px; border: 1px solid #dfe8f0; border-radius: 6px; background: #fff; overflow: hidden; }
            td { display: flex; justify-content: space-between; gap: 12px; border-bottom: 1px solid #edf2f6; font-size: 12px; text-align: right; }
            td::before { content: attr(data-label); flex: 0 0 95px; color: #607386; font-weight: 700; text-align: left; }
            td:last-child { border-bottom: 0; }
            td.payload-cell { display: block; text-align: left; }
            td.payload-cell::before { display: block; margin-bottom: 6px; }
        }
    </style>
</head>
<body>
<?php include('../../topo.php'); ?>
<?php
$today = date('Y-m-d');
$start = $_GET['data_inicial'] ?? date('Y-m-d', strtotime('-7 days'));
$end = $_GET['data_final'] ?? $today;
$status = $_GET['status'] ?? 'erros';
$search = trim($_GET['busca'] ?? '');

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start)) $start = date('Y-m-d', strtotime('-7 days'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) $end = $today;
if (!in_array($status, ['todos', 'erros', 'liquidados', 'conciliados'], true)) $status = 'erros';

$where = "servico = 'sicredi' AND data BETWEEN ? AND ?";
$params = [$start . ' 00:00:00', $end . ' 23:59:59'];
$types = 'ss';

if ($status === 'erros') {
    $where .= " AND resposta NOT LIKE 'LIQUIDADO%'";
} elseif ($status === 'liquidados') {
    $where .= " AND resposta LIKE 'LIQUIDADO%'";
} elseif ($status === 'conciliados') {
    $where .= " AND resposta = 'LIQUIDADO CONCILIADO'";
}

if ($search !== '') {
    $where .= " AND (id LIKE ? OR resposta LIKE ? OR dados LIKE ?)";
    $like = '%' . $search . '%';
    array_push($params, $like, $like, $like);
    $types .= 'sss';
}

$sql = "SELECT id, data, dados, resposta
        FROM sis_notificacoes
        WHERE $where
        ORDER BY id DESC
        LIMIT 300";

$stmt = mysqli_prepare($link, $sql);
mysqli_stmt_bind_param($stmt, $types, ...$params);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$rows = [];
$total = 0;
$erros = 0;
$liquidados = 0;
$conciliados = 0;
while ($row = mysqli_fetch_assoc($result)) {
    $payload = json_decode((string) $row['dados'], true);
    if (!is_array($payload)) {
        $payload = [];
    }

    $row['payload'] = $payload;
    $rows[] = $row;
    $total++;

    if (strpos((string) $row['resposta'], 'LIQUIDADO') === 0) $liquidados++;
    if ((string) $row['resposta'] === 'LIQUIDADO CONCILIADO') $conciliados++;
    if (strpos((string) $row['resposta'], 'LIQUIDADO') !== 0) $erros++;
}
?>
<div class="wrap">
    <div class="panel titlebar">
        <div class="title-copy">
            <img class="sicredi-logo" src="sicredi-logo.svg" alt="Sicredi">
            <div>
                <h1>Sicredi API - Logs</h1>
                <div class="muted">Webhooks recebidos, respostas do MK Auth e payload completo para troubleshooting.</div>
            </div>
        </div>
        <div class="no-print">
            <a class="btn btn-light" href="/admin/addons/rel_conciliacao_sicredi/">Baixas</a>
            <a class="btn btn-light" href="/admin/addons/log_sicredi_api/">Atualizar</a>
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
            <label>Status</label>
            <select name="status">
                <option value="erros" <?php echo $status === 'erros' ? 'selected' : ''; ?>>Erros/Rejeitados</option>
                <option value="todos" <?php echo $status === 'todos' ? 'selected' : ''; ?>>Todos</option>
                <option value="liquidados" <?php echo $status === 'liquidados' ? 'selected' : ''; ?>>Liquidados</option>
                <option value="conciliados" <?php echo $status === 'conciliados' ? 'selected' : ''; ?>>Conciliados</option>
            </select>
        </div>
        <div class="field">
            <label>Busca</label>
            <input type="text" name="busca" value="<?php echo h($search); ?>" placeholder="ID, nosso numero, movimento, resposta">
        </div>
        <div class="filter-action">
            <button class="btn btn-primary" type="submit">Filtrar</button>
        </div>
    </form>

    <div class="panel summary">
        <div class="metric"><span>Registros</span><b><?php echo $total; ?></b></div>
        <div class="metric"><span>Erros/Rejeitados</span><b><?php echo $erros; ?></b></div>
        <div class="metric"><span>Liquidados</span><b><?php echo $liquidados; ?></b></div>
        <div class="metric"><span>Conciliados</span><b><?php echo $conciliados; ?></b></div>
    </div>

    <div class="panel">
        <div class="scroll">
            <table>
                <thead>
                <tr>
                    <th>ID/Data</th>
                    <th>Resposta</th>
                    <th>Nosso No.</th>
                    <th>Movimento</th>
                    <th>Valor</th>
                    <th>Titulo empresa</th>
                    <th>Evento</th>
                    <th>Payload</th>
                </tr>
                </thead>
                <tbody>
                <?php
                if (!$rows) {
                    echo '<tr><td colspan="8">Nenhum webhook encontrado no filtro informado.</td></tr>';
                }

                foreach ($rows as $row) {
                    $payload = $row['payload'];
                    $resposta = (string) $row['resposta'];
                    $badgeClass = strpos($resposta, 'LIQUIDADO CONCILIADO') === 0 ? 'badge warn' : (strpos($resposta, 'LIQUIDADO') === 0 ? 'badge ok' : 'badge err');
                    $nosso = payload_value($payload, 'nossoNumero');
                    $movimento = payload_value($payload, 'movimento');
                    $valor = payload_value($payload, 'valorLiquidacao');
                    $tituloEmpresa = payload_value($payload, 'idTituloEmpresa');
                    $evento = payload_value($payload, 'idEventoWebhook');
                    $dataEvento = payload_date(payload_value($payload, 'dataEvento'));

                    echo '<tr>';
                    echo '<td data-label="ID/Data"><a href="/admin/notificacao.hhvm?id=' . h($row['id']) . '" target="_blank">#' . h($row['id']) . '</a><br><span class="muted">' . h(date_br($row['data'], true)) . '</span></td>';
                    echo '<td data-label="Resposta"><span class="' . h($badgeClass) . '">' . h($resposta) . '</span></td>';
                    echo '<td data-label="Nosso No.">' . h($nosso) . '</td>';
                    echo '<td data-label="Movimento">' . h($movimento) . '<br><span class="muted">' . h($dataEvento) . '</span></td>';
                    echo '<td data-label="Valor">' . h(money_br($valor)) . '</td>';
                    echo '<td data-label="Titulo empresa">' . h($tituloEmpresa) . '</td>';
                    echo '<td data-label="Evento">' . h($evento) . '</td>';
                    echo '<td data-label="Payload" class="payload-cell"><details><summary>Ver JSON</summary><pre>' . h(pretty_json($row['dados'])) . '</pre></details></td>';
                    echo '</tr>';
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
