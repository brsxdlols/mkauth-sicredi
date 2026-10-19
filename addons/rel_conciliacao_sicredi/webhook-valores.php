<?php

function sicredi_encargos_eventos(array $events): array
{
    $latest = null;
    $payload = null;
    foreach ($events as $event) {
        $decoded = json_decode((string) $event['dados'], true);
        if (!is_array($decoded) || strpos((string) ($decoded['movimento'] ?? ''), 'LIQUIDACAO_') !== 0) continue;
        $compare = $latest === null ? 1 : strcmp((string) $event['data'], (string) $latest['data']);
        if ($compare > 0 || ($compare === 0 && (int) $event['id'] > (int) $latest['id'])) {
            $latest = $event;
            $payload = $decoded;
        }
    }
    $result = ['juros' => null, 'multa' => null];
    foreach (['juros' => 'valorJuros', 'multa' => 'valorMulta'] as $key => $field) {
        if (isset($payload[$field]) && is_numeric($payload[$field])) {
            $value = (float) $payload[$field];
            if (is_finite($value) && $value >= 0) $result[$key] = $value;
        }
    }
    return $result;
}
