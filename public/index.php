<?php
/**
 * Version web (visuelle) de index.php.
 *
 * Contraintes:
 * - Ne pas exécuter index.php en sous-processus.
 * - Montrer visuellement ce qui se passe pour la RAM (used/alloc/pic) et le tri Top 3.
 *
 * Utilise AnalysisOrchestrator pour éliminer la duplication du workflow avec index.php / results.php.
 *
 * @author  Margot Hourdillé
 * @project RGBMatch — Challenge Firstruner
 * @version 1.0.0
 * @date    2026-03-06
 * @update  2026-03-06
 */

require_once __DIR__ . '/../app/bootstrap.php';

use RGBMatch\Api\UnsplashApiClient;
use RGBMatch\Application\IndexVisualPageRenderer;
use RGBMatch\Application\IsolatedMeasurementPayloadProvider;
use RGBMatch\Singletons\ConfigurationManager;

$baseUrlPath = app_base_url_path();

$wantsJson = isset($_GET['format']) && (string) $_GET['format'] === 'json';
$wantsRun = isset($_GET['run']) && (string) $_GET['run'] === '1';
$wantsSetup = isset($_GET['setup']) && (string) $_GET['setup'] === '1';

/**
 * Lit un payload JSON depuis php://input.
 *
 * @return array<string,mixed>
 */
function read_json_body(): array
{
    $raw = file_get_contents('php://input');
    if (!is_string($raw) || trim($raw) === '') {
        return [];
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

/**
 * Exécute le setup (téléchargement Unsplash) en mode non-interactif.
 *
 * @return array{downloadedTests:int, query:string, seconds:float, originWritten:bool}
 */
function run_setup_non_interactive(int $count, string $query = ''): array
{
    $setupStart = microtime(true);

    $config = ConfigurationManager::getInstance();
    $accessKey = (string) $config->get('unsplash.access_key');
    $apiUrl = (string) $config->get('unsplash.api_url');
    $testDir = (string) $config->get('paths.test');
    $originPath = (string) $config->get('paths.origin');

    if ($accessKey === '') {
        throw new RuntimeException('Clé API Unsplash non configurée. Vérifiez votre fichier .env');
    }

    // Nettoyage des anciennes images (réinitialisation)
    $oldImages = glob($testDir . '/*.jpg');
    if (is_array($oldImages)) {
        foreach ($oldImages as $oldImage) {
            if (is_file($oldImage)) {
                @unlink($oldImage);
            }
        }
    }
    if ($originPath !== '' && is_file($originPath)) {
        @unlink($originPath);
    }

    // Téléchargements
    $apiClient = new UnsplashApiClient($accessKey, $apiUrl, $testDir);

    // Origine
    $originWritten = false;
    $originPaths = $apiClient->downloadRandomImages(1, $query);
    if (!empty($originPaths) && isset($originPaths[0]) && is_string($originPaths[0]) && is_file($originPaths[0])) {
        $originWritten = @rename($originPaths[0], $originPath);
    }
    if (!$originWritten) {
        throw new RuntimeException("Impossible de télécharger l'image d'origine");
    }

    // Images tests
    $paths = $apiClient->downloadRandomImages($count, $query);
    $downloadedCount = is_array($paths) ? count($paths) : 0;

    return [
        'downloadedTests' => (int) $downloadedCount,
        'query' => (string) $query,
        'seconds' => (float) (microtime(true) - $setupStart),
        'originWritten' => (bool) $originWritten,
    ];
}

/**
 * Construit le payload JSON pour le run (déléguant à AnalysisOrchestrator).
 *
 * @return array{images: array, maxAbs: array{used:int, alloc:int, peak:int}}
 */
function build_run_payload(): array
{
    $provider = new IsolatedMeasurementPayloadProvider(dirname(__DIR__));
    return $provider->getPayload([
        'sampleRate' => 10,
        'maxDimension' => 0,
        'permCount' => 6,
    ]);
}

try {
    $config = ConfigurationManager::getInstance();
    $originPath = (string) $config->get('paths.origin');
    $testDir = (string) $config->get('paths.test');

    if ($wantsJson && $wantsSetup) {
        // Endpoint JSON pour lancer setup depuis la page web
        header('Content-Type: application/json; charset=utf-8');
        set_time_limit(0);

        $body = read_json_body();

        $count = isset($body['count']) ? (int) $body['count'] : (isset($_GET['count']) ? (int) $_GET['count'] : 10);
        $query = isset($body['query']) ? (string) $body['query'] : (isset($_GET['query']) ? (string) $_GET['query'] : '');
        $query = trim($query);

        $minCount = 6;
        $maxCount = 30;
        if ($count < $minCount || $count > $maxCount) {
            http_response_code(400);
            echo json_encode([
                'error' => true,
                'message' => "Nombre d'images invalide (min {$minCount}, max {$maxCount})",
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return;
        }

        $result = run_setup_non_interactive($count, $query);
        echo json_encode([
            'ok' => true,
            'result' => $result,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return;
    }

    if ($wantsJson && $wantsRun) {
        $payload = build_run_payload();
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return;
    }

    $originExists = ($originPath !== '' && file_exists($originPath));
    $testCount = 0;
    $testPaths = glob($testDir . '/*.jpg');
    if (is_array($testPaths)) {
        $testCount = count($testPaths);
    }

    $runJsonUrl = $baseUrlPath . '/public/index.php?run=1&format=json';
    $setupJsonUrl = $baseUrlPath . '/public/index.php?setup=1&format=json';
    $resultsUrl = $baseUrlPath . '/results.php';
    $renderer = new IndexVisualPageRenderer();
    echo $renderer->renderShell($baseUrlPath, $originPath, $runJsonUrl, $resultsUrl, $setupJsonUrl, $originExists, $testCount);
} catch (Throwable $e) {
    http_response_code(500);

    if ($wantsJson) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'error' => true,
            'message' => (string) $e->getMessage(),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return;
    }

    $msg = htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    echo "<h1>Erreur</h1><p>{$msg}</p>";
}
