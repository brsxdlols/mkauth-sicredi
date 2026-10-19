#!/usr/bin/env /opt/php8/bin/php
<?php
declare(strict_types=1);

// Reutiliza credenciais locais e autenticacao, sem executar baixas manuais.
define('SICREDI_CLIENT_ONLY', true);
require __DIR__ . '/enviar_baixa_manual_sicredi.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

function tarifaDay($value): string
{
    if (is_array($value) && count($value) >= 3) {
        return sprintf('%04d-%02d-%02d', $value[0], $value[1], $value[2]);
    }
    $ts = strtotime((string) $value);
    return $ts === false ? '' : date('Y-m-d', $ts);
}

function tarifaKey($agencia, $posto, $beneficiario, $nosso, $day): string
{
    return implode('|', [str_pad(onlyDigits($agencia), 4, '0', STR_PAD_LEFT),
        str_pad(onlyDigits($posto), 2, '0', STR_PAD_LEFT),
        str_pad(onlyDigits($beneficiario), 5, '0', STR_PAD_LEFT),
        str_pad(onlyDigits($nosso), 9, '0', STR_PAD_LEFT), $day]);
}

function tarifaMovimentos(array $carteira, string $day): array
{
    $token = authToken($carteira);
    $result = [];
    $page = 0;
    do {
        $query = http_build_query(['codigoBeneficiario' => $carteira['beneficiario'],
            'cooperativa' => $carteira['cooperativa'], 'posto' => $carteira['posto'],
            'dataLancamento' => date('d/m/Y', strtotime($day)),
            'tipoMovimento' => 'DEBITO', 'pagina' => $page]);
        $response = httpRequest('GET', SICREDI_BASE_URL . '/cobranca/v1/cobranca-financeiro/movimentacoes/?' . $query,
            ['Authorization: Bearer ' . $token, 'x-api-key: ' . $carteira['x_api_key'],
             'Content-Type: application/x-www-form-urlencoded']);
        $json = decodeJson($response['body']);
        if ($response['http_code'] !== 200 || !isset($json['resultado']) || !is_array($json['resultado'])
            || !isset($json['totalPaginas'])) {
            throw new RuntimeException('Consulta financeira HTTP ' . $response['http_code'] . ' carteira=' . $carteira['id'] . ' dia=' . $day);
        }
        $pages = (int) $json['totalPaginas'];
        if ($pages < 0 || $pages > 1000) throw new RuntimeException('Paginacao financeira invalida');
        foreach ($json['resultado'] as $movimento) $result[] = $movimento;
        $page++;
    } while ($page < $pages);
    return $result;
}

// Associa a tarifa a um webhook da mesma carteira, boleto e data de movimento.
// Nenhuma tarifa e inferida do valor nominal, juros, multa ou desconto.
$events = [];
$window = $days + 7;
$stmt = $mysqli->prepare("SELECT dados FROM sis_notificacoes WHERE servico='sicredi' AND data >= DATE_SUB(NOW(), INTERVAL ? DAY)");
$stmt->bind_param('i', $window);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $d = decodeJson($row['dados']);
    if (!isset($d['agencia'], $d['posto'], $d['beneficiario'], $d['nossoNumero'], $d['dataEvento'])
        || strpos((string) ($d['movimento'] ?? ''), 'LIQUIDACAO_') !== 0) continue;
    if (!preg_match('/^MKAUTH(\d+)/', (string) ($d['idTituloEmpresa'] ?? ''), $match)) continue;
    $key = tarifaKey($d['agencia'], $d['posto'], $d['beneficiario'], $d['nossoNumero'], tarifaDay($d['dataEvento']));
    $events[$key][$match[1]] = true;
}
$wallets = $mysqli->query("SELECT id FROM sis_boleto WHERE banco='sicrediapi.php' ORDER BY id")->fetch_all(MYSQLI_ASSOC);
$groups = [];
$errors = 0;
foreach ($wallets as $wallet) {
    try {
        $carteira = loadCarteira($mysqli, (int) $wallet['id']);
        for ($i = 0; $i < $days; $i++) {
            $day = date('Y-m-d', strtotime('-' . $i . ' days'));
            foreach (tarifaMovimentos($carteira, $day) as $m) {
                if (($m['tipoMovimento'] ?? '') !== 'DEBITO'
                    || strtoupper(trim((string) ($m['descMovimento'] ?? ''))) !== 'TARIFA ENTRADA/LIQUIDACAO') continue;
                if (!isset($m['agencia'], $m['posto'], $m['beneficiario'], $m['nossoNumero'], $m['dataMovimento'], $m['valorMovimento'])
                    || !is_numeric($m['valorMovimento']) || (float) $m['valorMovimento'] <= 0) continue;
                $key = tarifaKey($m['agencia'], $m['posto'], $m['beneficiario'], $m['nossoNumero'], tarifaDay($m['dataMovimento']));
                $expected = tarifaKey($carteira['cooperativa'], $carteira['posto'], $carteira['beneficiario'], $m['nossoNumero'], tarifaDay($m['dataMovimento']));
                if ($key !== $expected) throw new RuntimeException('Movimento retornou carteira divergente');
                // Mais de um debito para a mesma liquidacao exige revisao, sem somar ou escolher arbitrariamente.
                $groups[$key][] = ['carteira' => (int) $carteira['id'], 'movimento' => $m];
            }
        }
    } catch (Throwable $e) {
        fwrite(STDERR, $e->getMessage() . "\n");
        $errors++;
    }
}
if ($errors) { fwrite(STDERR, "Consulta incompleta. Nenhuma tarifa alterada.\n"); exit(2); }

$select = $mysqli->prepare("SELECT id,login,tarifa_paga,datapag FROM sis_lanc WHERE id_empresa=? AND nossonum=? AND status='pago' AND coletor='sicrediapi' AND deltitulo=0");
$plans = [];
foreach ($groups as $key => $entries) {
    if (count($entries) !== 1 || count($events[$key] ?? []) !== 1) {
        echo "IGNORADO: vinculo ausente ou ambiguo para movimento financeiro\n";
        continue;
    }
    $entry = $entries[0]; $m = $entry['movimento'];
    $empresa = (string) array_key_first($events[$key]);
    $nosso = str_pad(onlyDigits($m['nossoNumero']), 9, '0', STR_PAD_LEFT);
    $select->bind_param('ss', $empresa, $nosso); $select->execute();
    $titles = $select->get_result()->fetch_all(MYSQLI_ASSOC);
    if (count($titles) !== 1) { echo "IGNORADO: titulo ausente ou ambiguo\n"; continue; }
    $t = $titles[0]; $fee = money2($m['valorMovimento']);
    $plans[] = ['titulo' => $t, 'tarifa' => $fee, 'evidencia' => $entry];
    echo 'titulo=' . $t['id'] . ' carteira=' . $entry['carteira'] . ' tarifa_atual=' . $t['tarifa_paga'] . ' tarifa_api=' . $fee . ' data=' . $m['dataLancamento'] . "\n";
}
$counts = [];
foreach ($plans as $plan) $counts[$plan['titulo']['id']] = ($counts[$plan['titulo']['id']] ?? 0) + 1;
$plans = array_values(array_filter($plans, function ($plan) use ($counts) {
    return $counts[$plan['titulo']['id']] === 1;
}));
echo ($apply ? 'MODO APLICACAO' : 'MODO SIMULACAO') . ': ' . count($plans) . " tarifa(s) comprovada(s) pela API.\n";
if (!$apply || !$plans) exit(0);

// Serializa as correcoes de tarifa. So altera saidas criadas pelo addon.
$lock = $mysqli->query("SELECT GET_LOCK('sicredi_tarifas_api',0) acquired")->fetch_assoc();
if ((int) $lock['acquired'] !== 1) exit(0);
try {
    $mysqli->query("CREATE TABLE IF NOT EXISTS sicredi_tarifa_api_log (
        titulo_id INT NOT NULL PRIMARY KEY, carteira_id INT NOT NULL,
        tarifa_anterior DECIMAL(15,2) NOT NULL, tarifa_api DECIMAL(15,2) NOT NULL,
        evidencia MEDIUMTEXT NOT NULL, updated_at DATETIME NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8");
    foreach ($plans as $plan) {
        $t = $plan['titulo']; $id = (int) $t['id']; $fee = (float) $plan['tarifa'];
        $history = 'Tarifa Sicredi do titulo ' . $id . ' / ' . $t['login'];
        $mysqli->begin_transaction();
        try {
            $q = $mysqli->prepare("SELECT id,usuario,historico,entrada,saida FROM sis_caixa WHERE historico LIKE CONCAT('%Tarifa Sicredi do titulo ', ?, ' /%') OR historico LIKE CONCAT('%Tarifa titulo ', ?, ' via%') OR historico LIKE CONCAT('%Tarifa titulo ', ?, ' no arq.%') FOR UPDATE");
            $q->bind_param('iii', $id, $id, $id); $q->execute(); $cash = $q->get_result()->fetch_all(MYSQLI_ASSOC);
            if (count($cash) > 1 || ($cash && ($cash[0]['usuario'] !== 'sicrediapi' || $cash[0]['historico'] !== $history || (float) $cash[0]['entrada'] !== 0.0))) {
                throw new RuntimeException('Caixa existente exige revisao: titulo=' . $id);
            }
            $q = $mysqli->prepare("UPDATE sis_lanc SET tarifa_paga=? WHERE id=? AND status='pago' AND coletor='sicrediapi' AND deltitulo=0 AND tarifa_paga <=> ?");
            $old = $t['tarifa_paga']; $q->bind_param('dis', $fee, $id, $old); $q->execute();
            // Revalida o titulo, inclusive quando o UPDATE nao muda o valor.
            $q = $mysqli->prepare("SELECT tarifa_paga FROM sis_lanc WHERE id=? AND status='pago' AND coletor='sicrediapi' AND deltitulo=0 FOR UPDATE");
            $q->bind_param('i', $id); $q->execute(); $current = $q->get_result()->fetch_assoc();
            if (!$current || money2($current['tarifa_paga']) !== money2($fee)) throw new RuntimeException('Titulo mudou durante conciliacao: ' . $id);
            if ($cash) {
                $cashId = (int) $cash[0]['id'];
                $q = $mysqli->prepare("UPDATE sis_caixa SET saida=? WHERE id=?"); $q->bind_param('di', $fee, $cashId); $q->execute();
            } else {
                $uuid = uuid4(); $data = tarifaDay($plan['evidencia']['movimento']['dataLancamento']);
                $q = $mysqli->prepare("INSERT INTO sis_caixa (uuid_caixa,usuario,data,historico,complemento,entrada,saida,tipomov,planodecontas) VALUES (?,'sicrediapi',?,?,'',0,?,'aut','Outros')");
                $q->bind_param('sssd', $uuid, $data, $history, $fee); $q->execute();
            }
            // Evidencia sem dados pessoais do pagador nem credenciais.
            $m = array_intersect_key($plan['evidencia']['movimento'], array_flip(['agencia','posto','beneficiario','nossoNumero','dataMovimento','dataLancamento','valorMovimento','tipoMovimento','descMovimento']));
            $evidence = json_encode($m, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $walletId = $plan['evidencia']['carteira']; $previous = (float) $old;
            $q = $mysqli->prepare("INSERT INTO sicredi_tarifa_api_log (titulo_id,carteira_id,tarifa_anterior,tarifa_api,evidencia,updated_at) VALUES (?,?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE carteira_id=VALUES(carteira_id),tarifa_api=VALUES(tarifa_api),evidencia=VALUES(evidencia),updated_at=NOW()");
            $q->bind_param('iidds', $id, $walletId, $previous, $fee, $evidence); $q->execute();
            $mysqli->commit();
        } catch (Throwable $e) { $mysqli->rollback(); fwrite(STDERR, $e->getMessage() . "\n"); $errors++; }
    }
} finally { $mysqli->query("SELECT RELEASE_LOCK('sicredi_tarifas_api')"); }
exit($errors ? 3 : 0);
