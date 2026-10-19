<?php
require dirname(__DIR__) . '/addons/rel_conciliacao_sicredi/webhook-valores.php';
function event($id, $payload) {
    return ['id' => $id, 'data' => '2026-10-19 10:00:00', 'dados' => json_encode($payload)];
}
$liquidacao = event(1, ['movimento' => 'LIQUIDACAO_COMPE_H5', 'valorJuros' => 2.43, 'valorMulta' => 1.80]);
$duplicate = $liquidacao; $duplicate['id'] = 2;
$manual = event(3, ['movimento' => 'PEDIDO_BAIXA_MANUAL', 'valorJuros' => 99]);
$values = sicredi_encargos_eventos([$liquidacao, $duplicate, $manual]);
if ($values !== ['juros' => 2.43, 'multa' => 1.80]) throw new RuntimeException('Encargos duplicados ou baixa manual usada');
$values = sicredi_encargos_eventos([event(4, ['movimento' => 'LIQUIDACAO_PIX', 'valorJuros' => 0, 'valorMulta' => 0])]);
if ($values !== ['juros' => 0.0, 'multa' => 0.0]) throw new RuntimeException('Zero informado perdido');
if (sicredi_encargos_eventos([$manual]) !== ['juros' => null, 'multa' => null]) throw new RuntimeException('Valor presumido');
$values = sicredi_encargos_eventos([event(5, ['movimento' => 'LIQUIDACAO_REDE', 'valorJuros' => 'invalido', 'valorMulta' => -1])]);
if ($values !== ['juros' => null, 'multa' => null]) throw new RuntimeException('Valor invalido aceito');
echo "PASS: juros e multa do webhook, duplicatas, baixa manual, zero e valores ausentes/invalidos.\n";
