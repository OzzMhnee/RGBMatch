<?php
/**
 * Worker HTTP local dédié à la génération du payload de mesures.
 *
 * Ce point d'entrée est réservé à localhost et n'a pas vocation à être exposé.
 *
 * @author  Margot Hourdillé
 * @project RGBMatch — Challenge Firstruner
 * @version 1.0.0
 * @date    2026-03-09
 * @update  2026-03-09
 */

require_once __DIR__ . '/../app/bootstrap.php';

use RGBMatch\Application\ServiceFactory;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

/**
 * @return array<string,mixed>
 */
function measurement_worker_read_json_body(): array
{
    $raw = file_get_contents('php://input');
    if (!is_string($raw) || trim($raw) === '') {
        return [];
    }

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function measurement_worker_is_local_request(): bool
{
    $remoteAddr = isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '';
    return in_array($remoteAddr, ['127.0.0.1', '::1'], true);
}

function measurement_worker_has_internal_header(): bool
{
    $header = isset($_SERVER['HTTP_X_RGBMATCH_WORKER']) ? trim((string) $_SERVER['HTTP_X_RGBMATCH_WORKER']) : '';
    return $header === '1';
}

function measurement_worker_int(array $source, string $key, int $default, int $minValue, int $maxValue): int
{
    if (!array_key_exists($key, $source) || filter_var($source[$key], FILTER_VALIDATE_INT) === false) {
        return $default;
    }

    $value = (int) $source[$key];
    if ($value < $minValue) {
        return $minValue;
    }
    if ($value > $maxValue) {
        return $maxValue;
    }

    return $value;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'error' => true,
        'message' => 'Méthode HTTP non autorisée pour le worker local.',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return;
}

if (!measurement_worker_is_local_request() || !measurement_worker_has_internal_header()) {
    http_response_code(403);
    echo json_encode([
        'error' => true,
        'message' => 'Accès refusé : worker réservé aux appels internes localhost.',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return;
}

try {
    $body = measurement_worker_read_json_body();
    $options = [
        'sampleRate' => measurement_worker_int($body, 'sampleRate', 10, 1, 1000),
        'maxDimension' => measurement_worker_int($body, 'maxDimension', 0, 0, 20000),
        'permCount' => measurement_worker_int($body, 'permCount', 6, 0, 1000),
    ];

    $provider = ServiceFactory::createMeasurementPayloadProvider(dirname(__DIR__));
    $payload = $provider->buildPayload($options);

    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'error' => true,
        'message' => (string) $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}