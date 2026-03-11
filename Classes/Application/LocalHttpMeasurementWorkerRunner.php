<?php
/**
 * Runner d'infrastructure basé sur un worker HTTP local partagé.
 *
 * @author  Margot Hourdillé
 * @project RGBMatch — Challenge Firstruner
 * @version 1.0.0
 * @date    2026-03-09
 * @update  2026-03-09
 */

namespace RGBMatch\Application;

use RuntimeException;
use RGBMatch\Interfaces\IMeasurementWorkerRunner;

final class LocalHttpMeasurementWorkerRunner implements IMeasurementWorkerRunner
{
    /** @var string */
    private $endpointPath;

    public function __construct(string $endpointPath = '/public/measurement_worker.php')
    {
        $this->endpointPath = $endpointPath;
    }

    /**
     * @param array{sampleRate:int,maxDimension:int,permCount:int} $options
     * @return array<string,mixed>
     */
    public function run(string $projectRoot, array $options): array
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('cURL est requis pour appeler le worker HTTP local.');
        }

        $url = $this->buildWorkerUrl($projectRoot);
        if (!$this->isEndpointReachable($url)) {
            throw new RuntimeException('Worker HTTP local non joignable.');
        }

        $payload = json_encode($options, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($payload)) {
            throw new RuntimeException('Impossible de sérialiser les options du worker HTTP.');
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => false,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 180,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json',
                'Cache-Control: no-store',
                'X-RGBMATCH-Worker: 1',
            ],
        ]);

        $response = curl_exec($ch);
        if ($response === false) {
            $message = curl_error($ch);
            $code = curl_errno($ch);
            curl_close($ch);
            throw new RuntimeException("Worker HTTP local indisponible ({$code}) : {$message}");
        }

        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $decoded = json_decode((string) $response, true);
        if ($httpCode < 200 || $httpCode >= 300) {
            $message = is_array($decoded) && isset($decoded['message']) ? (string) $decoded['message'] : 'Erreur HTTP ' . $httpCode;
            throw new RuntimeException('Le worker HTTP local a échoué : ' . $message);
        }

        if (!is_array($decoded)) {
            throw new RuntimeException('JSON invalide retourné par le worker HTTP local.');
        }

        return $decoded;
    }

    private function isEndpointReachable(string $url): bool
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_NOBODY => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => false,
            CURLOPT_CONNECTTIMEOUT => 1,
            CURLOPT_TIMEOUT => 2,
            CURLOPT_HTTPHEADER => [
                'X-RGBMATCH-Worker: 1',
            ],
        ]);

        $ok = curl_exec($ch);
        if ($ok === false) {
            curl_close($ch);
            return false;
        }

        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $httpCode > 0;
    }

    private function buildWorkerUrl(string $projectRoot): string
    {
        $baseUrl = $this->resolveBaseUrl($projectRoot);
        return rtrim($baseUrl, '/') . $this->endpointPath;
    }

    private function resolveBaseUrl(string $projectRoot): string
    {
        $configured = $_ENV['RGBMATCH_LOCAL_BASE_URL'] ?? getenv('RGBMATCH_LOCAL_BASE_URL') ?? '';
        $configured = trim((string) $configured);
        if ($configured !== '') {
            return rtrim($configured, '/');
        }

        $host = isset($_SERVER['HTTP_HOST']) ? trim((string) $_SERVER['HTTP_HOST']) : '';
        if ($host !== '') {
            $scheme = $this->isHttpsRequest() ? 'https' : 'http';
            $scriptName = isset($_SERVER['SCRIPT_NAME']) ? (string) $_SERVER['SCRIPT_NAME'] : '';
            $basePath = str_replace('\\', '/', rtrim(dirname($scriptName), '/'));
            if ($basePath !== '' && $basePath[0] !== '/') {
                $basePath = '/' . $basePath;
            }
            if ($basePath === '/' || $basePath === '.') {
                $basePath = '';
            }
            if ($basePath !== '' && substr($basePath, -7) === '/public') {
                $basePath = substr($basePath, 0, -7);
            }

            return $scheme . '://' . $host . $basePath;
        }

        return 'http://localhost/' . basename(rtrim($projectRoot, DIRECTORY_SEPARATOR));
    }

    private function isHttpsRequest(): bool
    {
        if (!isset($_SERVER['HTTPS'])) {
            return false;
        }

        $https = strtolower((string) $_SERVER['HTTPS']);
        return $https !== '' && $https !== 'off' && $https !== '0';
    }
}