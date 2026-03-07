<?php

require_once __DIR__ . '/bootstrap.php';

use RGBMatch\Application\IsolatedMeasurementPayloadProvider;

try {
    $provider = new IsolatedMeasurementPayloadProvider(dirname(__DIR__));
    $payload = $provider->buildPayload([
        'sampleRate' => 10,
        'maxDimension' => 0,
        'permCount' => 6,
    ]);

    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
}