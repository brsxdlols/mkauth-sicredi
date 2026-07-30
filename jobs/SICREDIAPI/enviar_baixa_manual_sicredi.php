#!/usr/bin/env /opt/php8/bin/php
<?php
declare(strict_types=1);

const DB_HOST = '127.0.0.1';
const DB_USER = 'root';
const DB_PASS = 'vertrigo';
const DB_NAME = 'mkradius';
const SICREDI_BASE_URL = 'https://api-parceiro.sicredi.com.br';
const TOKEN_CACHE_DIR = '/opt/mk-auth/cache/tokens';

$apply = in_array('--apply', $argv, true);
$days = 15;
$limit = 20;
foreach ($argv as $arg) {
    if (preg_match('/^--days=(\d+)$/', $arg, $m)) {
        $days = max(1, (int) $m[1]);
    }
    if (preg_match('/^--limit=(\d+)$/', $arg, $m)) {
        $limit = max(1, min(100, (int) $m[1]));
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

function onlyDigits($value): string
{
    return preg_replace('/\D+/', '', (string) $value);
}

function uuid4(): string
{
    $data = random_bytes(16);
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

function httpRequest(string $method, string $url, array $headers, ?string $body = null): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => false,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT => 45,
    ]);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }
    $response = curl_exec($ch);
    $error = curl_error($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [
        'http_code' => $httpCode,
        'body' => $response === false ? '' : (string) $response,
        'error' => $error,
    ];
}

function decodeJson(string $json): array
{
    $decoded = json_decode($json, true);
    return is_array($decoded) ? $decoded : [];
}

function nowSql(): string
{
    return date('Y-m-d H:i:s');
}

function resolvedMessage(int $httpCode, string $body): bool
{
    $text = strtolower($body);
    if ($httpCode === 202) {
        return true;
    }
    return $httpCode === 422 && (
        strpos($text, 'ja baixado') !== false ||
        strpos($text, 'já baixado') !== false ||
        strpos($text, 'ja liquidado') !== false ||
        strpos($text, 'já liquidado') !== false
    );
}

function respostaStatus(int $httpCode, string $body): string
{
    if ($httpCode === 202) {
        return 'BAIXA MANUAL ENVIADA';
    }
    if (resolvedMessage($httpCode, $body)) {
        return 'BAIXA MANUAL JA RESOLVIDA';
    }
    return 'BAIXA MANUAL ERRO';
}

function createTables(mysqli $mysqli): void
{
    $mysqli->query(
        "CREATE TABLE IF NOT EXISTS sicredi_baixa_manual_log (
            id INT NOT NULL AUTO_INCREMENT,
            titulo_id INT NOT NULL,
            nosso_numero VARCHAR(20) NOT NULL,
            login VARCHAR(100) NOT NULL,
            valor DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            status VARCHAR(40) NOT NULL,
            http_code INT NULL,
            transaction_id VARCHAR(80) NULL,
            resposta MEDIUMTEXT NULL,
            payload MEDIUMTEXT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_titulo_id (titulo_id),
            KEY idx_nosso_numero (nosso_numero),
            KEY idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8"
    );
}

function tableExists(mysqli $mysqli, string $table): bool
{
    $stmt = $mysqli->prepare(
        "SELECT COUNT(*) AS total
           FROM information_schema.TABLES
          WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = ?"
    );
    $stmt->bind_param('s', $table);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return (int) ($row['total'] ?? 0) > 0;
}

function loadCarteira(mysqli $mysqli): array
{
    $sql = "SELECT id, agencia, codigo_cliente, ponto_venda, token, token_bcash, nome, banco, utilizar
              FROM sis_boleto
             WHERE banco = 'sicrediapi.php'
                OR utilizar = 'bancoapi'
                OR nome LIKE '%Sicred%'
             ORDER BY banco = 'sicrediapi.php' DESC, utilizar = 'bancoapi' DESC, id DESC
             LIMIT 1";
    $result = $mysqli->query($sql);
    $row = $result ? $result->fetch_assoc() : null;
    if (!$row) {
        throw new RuntimeException('carteira Sicredi API nao encontrada em sis_boleto');
    }

    $row['cooperativa'] = str_pad(onlyDigits($row['agencia'] ?? ''), 4, '0', STR_PAD_LEFT);
    $row['posto'] = str_pad(onlyDigits($row['ponto_venda'] ?? ''), 2, '0', STR_PAD_LEFT);
    $row['beneficiario'] = str_pad(onlyDigits($row['codigo_cliente'] ?? ''), 5, '0', STR_PAD_LEFT);
    $row['x_api_key'] = trim((string) ($row['token_bcash'] ?? ''));
    $row['codigo_acesso'] = trim((string) ($row['token'] ?? ''));

    if ($row['cooperativa'] === '' || $row['posto'] === '' || $row['beneficiario'] === '') {
        throw new RuntimeException('carteira Sicredi API sem cooperativa/posto/beneficiario');
    }
    if ($row['x_api_key'] === '' || $row['codigo_acesso'] === '') {
        throw new RuntimeException('carteira Sicredi API sem x-api-key ou codigo de acesso');
    }

    return $row;
}

function cachePath(array $carteira): string
{
    return rtrim(TOKEN_CACHE_DIR, '/') . '/' . (int) $carteira['id'] . '@sicredi.json';
}

function tokenFromCache(array $carteira): ?string
{
    $path = cachePath($carteira);
    if (!is_file($path)) {
        return null;
    }
    $data = decodeJson((string) file_get_contents($path));
    $token = (string) ($data['access_token'] ?? $data['accessToken'] ?? '');
    if ($token === '') {
        return null;
    }

    $expiresAt = (int) ($data['expires_at'] ?? $data['expiresAt'] ?? 0);
    if (!$expiresAt && isset($data['created_at'], $data['expires_in'])) {
        $expiresAt = (int) $data['created_at'] + (int) $data['expires_in'];
    }
    if (!$expiresAt && isset($data['createdAt'], $data['expires_in'])) {
        $expiresAt = (int) $data['createdAt'] + (int) $data['expires_in'];
    }
    if ($expiresAt && $expiresAt <= time() + 30) {
        return null;
    }

    return $token;
}

function saveTokenCache(array $carteira, array $token): void
{
    $dir = rtrim(TOKEN_CACHE_DIR, '/');
    if (!is_dir($dir)) {
        mkdir($dir, 0750, true);
    }
    $payload = $token;
    $payload['created_at'] = time();
    $payload['expires_at'] = time() + (int) ($token['expires_in'] ?? 240);
    file_put_contents(cachePath($carteira), json_encode($payload, JSON_UNESCAPED_SLASHES));
}

function authToken(array $carteira): string
{
    $cached = tokenFromCache($carteira);
    if ($cached) {
        return $cached;
    }

    $username = $carteira['beneficiario'] . $carteira['cooperativa'];
    $body = http_build_query([
        'grant_type' => 'password',
        'username' => $username,
        'password' => $carteira['codigo_acesso'],
        'scope' => 'cobranca',
    ]);
    $response = httpRequest('POST', SICREDI_BASE_URL . '/auth/openapi/token', [
        'x-api-key: ' . $carteira['x_api_key'],
        'context: COBRANCA',
        'Content-Type: application/x-www-form-urlencoded',
    ], $body);
    $json = decodeJson($response['body']);
    $token = (string) ($json['access_token'] ?? '');
    if ($response['http_code'] !== 200 || $token === '') {
        throw new RuntimeException('falha ao autenticar Sicredi HTTP ' . $response['http_code'] . ' ' . $response['body'] . ' ' . $response['error']);
    }
    saveTokenCache($carteira, $json);
    return $token;
}

function enviarBaixa(array $carteira, string $accessToken, string $nossoNumero): array
{
    $url = SICREDI_BASE_URL . '/cobranca/boleto/v1/boletos/' . rawurlencode($nossoNumero) . '/baixa';
    return httpRequest('PATCH', $url, [
        'Authorization: Bearer ' . $accessToken,
        'x-api-key: ' . $carteira['x_api_key'],
        'Content-Type: application/json',
        'cooperativa: ' . $carteira['cooperativa'],
        'posto: ' . $carteira['posto'],
        'codigoBeneficiario: ' . $carteira['beneficiario'],
    ], '{}');
}

function insertNotificacao(mysqli $mysqli, array $titulo, array $carteira, int $httpCode, string $responseBody, string $status): void
{
    $payload = [
        'tipo' => 'PEDIDO_BAIXA_MANUAL',
        'movimento' => 'PEDIDO_BAIXA_MANUAL',
        'nossoNumero' => $titulo['nossonum'],
        'idTituloEmpresa' => $titulo['id_empresa'] ? 'MKAUTH' . $titulo['id_empresa'] . 'GGGGGGGGG' : '',
        'titulo' => (int) $titulo['id'],
        'login' => $titulo['login'],
        'cliente' => $titulo['nome'],
        'valorLiquidacao' => (float) $titulo['valorpag'],
        'dataEvento' => nowSql(),
        'cooperativa' => $carteira['cooperativa'],
        'posto' => $carteira['posto'],
        'codigoBeneficiario' => $carteira['beneficiario'],
        'httpCode' => $httpCode,
        'respostaApi' => decodeJson($responseBody) ?: $responseBody,
    ];
    $dados = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $stmt = $mysqli->prepare("INSERT INTO sis_notificacoes (data, servico, dados, resposta) VALUES (?, 'sicredi', ?, ?)");
    $data = nowSql();
    $stmt->bind_param('sss', $data, $dados, $status);
    $stmt->execute();
}

function upsertLog(mysqli $mysqli, array $titulo, string $status, ?int $httpCode = null, ?string $transactionId = null, ?string $responseBody = null, ?string $payload = null): void
{
    $stmt = $mysqli->prepare(
        "INSERT INTO sicredi_baixa_manual_log
                (titulo_id, nosso_numero, login, valor, status, http_code, transaction_id, resposta, payload, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
         ON DUPLICATE KEY UPDATE
                status = VALUES(status),
                http_code = VALUES(http_code),
                transaction_id = VALUES(transaction_id),
                resposta = VALUES(resposta),
                payload = VALUES(payload),
                updated_at = NOW()"
    );
    $tituloId = (int) $titulo['id'];
    $valor = (float) $titulo['valorpag'];
    $nosso = (string) $titulo['nossonum'];
    $login = (string) $titulo['login'];
    $stmt->bind_param('issdsisss', $tituloId, $nosso, $login, $valor, $status, $httpCode, $transactionId, $responseBody, $payload);
    $stmt->execute();
}

if ($apply) {
    createTables($mysqli);
}
$hasLogTable = tableExists($mysqli, 'sicredi_baixa_manual_log');
$carteira = loadCarteira($mysqli);

$sql = "SELECT l.id, l.login, c.nome, l.nossonum, l.id_empresa, l.datavenc, l.processamento,
               l.datapag, l.valor, l.valorpag, l.coletor, l.formapag, l.referencia
          FROM sis_lanc l
     LEFT JOIN sis_cliente c ON c.login = l.login
     " . ($hasLogTable ? "LEFT JOIN sicredi_baixa_manual_log bl ON bl.titulo_id = l.id" : "") . "
         WHERE l.status = 'pago'
           AND l.deltitulo = 0
           AND l.datapag IS NOT NULL
           AND l.datapag >= DATE_SUB(NOW(), INTERVAL ? DAY)
           AND l.nossonum IS NOT NULL
           AND l.nossonum <> ''
           AND l.valorpag > 0
           AND LOWER(IFNULL(l.coletor, '')) NOT LIKE '%sicrediapi%'
           AND LOWER(IFNULL(l.coletor, '')) NOT LIKE '%retorno%'
           AND LOWER(IFNULL(l.formapag, '')) NOT LIKE '%boleto%'
           " . ($hasLogTable ? "AND (bl.id IS NULL OR bl.status IN ('ERRO', 'PENDENTE'))" : "") . "
         ORDER BY l.datapag ASC, l.id ASC
         LIMIT ?";
$stmt = $mysqli->prepare($sql);
$stmt->bind_param('ii', $days, $limit);
$stmt->execute();
$result = $stmt->get_result();

$candidatos = [];
while ($row = $result->fetch_assoc()) {
    $row['nossonum'] = str_pad(onlyDigits($row['nossonum']), 9, '0', STR_PAD_LEFT);
    if (strlen($row['nossonum']) !== 9) {
        continue;
    }
    $candidatos[] = $row;
}

echo ($apply ? "MODO APLICACAO\n" : "MODO SIMULACAO\n");
echo "Periodo analisado: {$days} dia(s)\n";
echo "Limite por execucao: {$limit}\n";
echo "Carteira: cooperativa={$carteira['cooperativa']} posto={$carteira['posto']} beneficiario={$carteira['beneficiario']}\n";
echo "Candidatos baixa manual: " . count($candidatos) . "\n";

foreach ($candidatos as $c) {
    echo sprintf(
        "titulo=%d login=%s nosso=%s pago=%s valor=%s forma=%s coletor=%s\n",
        $c['id'],
        $c['login'],
        $c['nossonum'],
        $c['datapag'],
        money2($c['valorpag']),
        $c['formapag'],
        $c['coletor']
    );
}

if (!$apply) {
    echo "Nada enviado ao banco. Use --apply para enviar pedidos de baixa.\n";
    exit(0);
}

if (!$candidatos) {
    echo "Nada para enviar.\n";
    exit(0);
}

$accessToken = authToken($carteira);
$enviados = 0;
$resolvidos = 0;
$erros = 0;

foreach ($candidatos as $c) {
    $requestPayload = json_encode([
        'endpoint' => '/cobranca/boleto/v1/boletos/' . $c['nossonum'] . '/baixa',
        'body' => new stdClass(),
    ], JSON_UNESCAPED_SLASHES);
    $response = enviarBaixa($carteira, $accessToken, (string) $c['nossonum']);
    $body = $response['body'] ?: $response['error'];
    $json = decodeJson($response['body']);
    $transactionId = (string) ($json['transactionId'] ?? '');
    $status = respostaStatus((int) $response['http_code'], $body);

    upsertLog($mysqli, $c, resolvedMessage((int) $response['http_code'], $body) ? 'RESOLVIDO' : 'ERRO', (int) $response['http_code'], $transactionId, $body, $requestPayload);
    insertNotificacao($mysqli, $c, $carteira, (int) $response['http_code'], $body, $status);

    if ($status === 'BAIXA MANUAL ENVIADA') {
        $enviados++;
    } elseif ($status === 'BAIXA MANUAL JA RESOLVIDA') {
        $resolvidos++;
    } else {
        $erros++;
    }

    echo sprintf(
        "titulo=%d nosso=%s http=%d status=%s transaction=%s\n",
        $c['id'],
        $c['nossonum'],
        $response['http_code'],
        $status,
        $transactionId ?: '-'
    );
}

echo "Pedidos enviados: {$enviados}; ja resolvidos: {$resolvidos}; erros: {$erros}\n";
