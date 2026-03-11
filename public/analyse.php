<?php
/**
 * Page d'analyse interactive (run + visualisation).
 *
 * Sert aussi les endpoints JSON pour le setup et le run.
 *
 * © 2026 Tous droits réservés.
 *
 * @author  Margot Hourdillé
 * @project RGBMatch — Challenge Firstruner
 * @version 2.0.0
 * @date    2026-03-06
 * @update  2026-03-11
 */

require_once __DIR__ . '/../app/bootstrap.php';

use RGBMatch\Api\UnsplashApiClient;
use RGBMatch\Application\IndexVisualPageRenderer;
use RGBMatch\IO\LockedJsonFileStore;
use RGBMatch\Application\ServiceFactory;
use RGBMatch\Security\RequestThrottle;
use RGBMatch\Singletons\ConfigurationManager;

/**
 * Sanitize un texte utilisateur simple provenant de GET/POST/JSON.
 */
function request_sanitize_text($value, int $maxLength = 255): string
{
    if (!is_scalar($value) && $value !== null) {
        return '';
    }

    $text = trim((string) $value);
    $text = preg_replace('/[[:cntrl:]]+/u', ' ', $text);
    if (!is_string($text)) {
        return '';
    }

    $text = preg_replace('/\s+/u', ' ', $text);
    if (!is_string($text)) {
        return '';
    }

    if ($maxLength > 0 && strlen($text) > $maxLength) {
        $text = substr($text, 0, $maxLength);
    }

    return trim($text);
}

/**
 * @param array<string,mixed> $source
 */
function request_flag(array $source, string $key, string $expectedValue): bool
{
    if (!array_key_exists($key, $source)) {
        return false;
    }

    return request_sanitize_text($source[$key], 16) === $expectedValue;
}

/**
 * @param array<int,array<string,mixed>> $sources
 */
function request_int(array $sources, string $key, int $default): int
{
    foreach ($sources as $source) {
        if (!array_key_exists($key, $source)) {
            continue;
        }

        if (filter_var($source[$key], FILTER_VALIDATE_INT) === false) {
            return $default;
        }

        return (int) $source[$key];
    }

    return $default;
}

/**
 * @param array<int,array<string,mixed>> $sources
 */
function request_text(array $sources, string $key, string $default = '', int $maxLength = 255): string
{
    foreach ($sources as $source) {
        if (array_key_exists($key, $source)) {
            return request_sanitize_text($source[$key], $maxLength);
        }
    }

    return $default;
}

function request_normalize_setup_query(string $query): string
{
    $query = str_replace(["\xE2\x80\x99", '’'], "'", $query);
    $query = preg_replace('/\s+/u', ' ', trim($query));

    return is_string($query) ? $query : '';
}

function request_validate_setup_query(string $query): ?string
{
    if ($query === '') {
        return null;
    }

    if (strlen($query) > 60) {
        return 'Le theme est trop long (60 caracteres maximum).';
    }

    if (preg_match('#[&;<>={}\[\]\\/]#u', $query)) {
        return 'Theme invalide. Supprimez les caracteres speciaux reserves.';
    }

    preg_match_all('/[\p{L}\p{N}]/u', $query, $matches);
    $charCount = isset($matches[0]) && is_array($matches[0]) ? count($matches[0]) : 0;
    if ($charCount < 2) {
        return 'Theme invalide. Utilisez au moins 2 caracteres alphanumeriques.';
    }

    $wordCount = preg_match_all('/[\p{L}\p{N}]+/u', $query, $wordMatches);
    if (is_int($wordCount) && $wordCount > 5) {
        return 'Theme invalide. Utilisez au maximum 5 mots.';
    }

    if (!preg_match('/\A[\p{L}\p{N}]+(?:[ \'-][\p{L}\p{N}]+)*\z/u', $query)) {
        return 'Theme invalide. Utilisez uniquement des lettres, chiffres, espaces, apostrophes ou tirets.';
    }

    return null;
}

/**
 * @return array{status:int,message:string}|null
 */
function setup_map_unsplash_error(\Throwable $error): ?array
{
    $message = (string) $error->getMessage();

    if (stripos($message, 'No photos found') !== false) {
        return [
            'status' => 400,
            'message' => 'Aucune image Unsplash trouvee pour ce theme. Essayez un mot plus simple ou plus courant.',
        ];
    }

    if (stripos($message, 'RateLimit') !== false || stripos($message, 'rate limit') !== false) {
        return [
            'status' => 429,
            'message' => 'Limite de requetes Unsplash atteinte. Attendez un peu avant de relancer le setup.',
        ];
    }

    return null;
}

function setup_request_client_key(): string
{
    $remoteAddr = isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : 'unknown';
    $userAgent = isset($_SERVER['HTTP_USER_AGENT']) ? (string) $_SERVER['HTTP_USER_AGENT'] : 'unknown';

    return sha1($remoteAddr . '|' . $userAgent);
}

/**
 * @return array{status:int,message:string}|null
 */
function setup_rate_limit_guard(): ?array
{
    $store = new LockedJsonFileStore();
    $throttle = new RequestThrottle($store, dirname(__DIR__) . '/storage/results/setup-throttle.json');
    $clientKey = setup_request_client_key();

    $shortWindow = $throttle->hit('setup-short:' . $clientKey, 1, 5, 5);
    if ($shortWindow['allowed'] === false) {
        return [
            'status' => 429,
            'message' => 'Setup trop frequemment relance. Attendez ' . $shortWindow['retryAfter'] . ' s avant de recommencer.',
        ];
    }

    $burstWindow = $throttle->hit('setup-burst:' . $clientKey, 3, 60, 60);
    if ($burstWindow['allowed'] === false) {
        return [
            'status' => 429,
            'message' => 'Trop de setups en peu de temps. Attendez ' . $burstWindow['retryAfter'] . ' s avant de recommencer.',
        ];
    }

    return null;
}

$baseUrlPath = app_base_url_path();

$wantsJson = request_flag($_GET, 'format', 'json');
$wantsRun = request_flag($_GET, 'run', '1');
$wantsSetup = request_flag($_GET, 'setup', '1');

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

    $apiClient = new UnsplashApiClient($accessKey, $apiUrl, $testDir);
    $setupService = new \RGBMatch\Application\SetupService($apiClient, $testDir, $originPath);
    $setupResult = $setupService->run($count, $query, false);

    return [
        'downloadedTests' => (int) $setupResult['downloadedTests'],
        'query' => (string) $query,
        'seconds' => (float) (microtime(true) - $setupStart),
        'originWritten' => (bool) $setupResult['originWritten'],
    ];
}

/**
 * Construit le payload JSON pour le run (déléguant à AnalysisOrchestrator).
 *
 * @return array{images: array, maxAbs: array{used:int, alloc:int, peak:int}}
 */
function build_run_payload(): array
{
    $provider = ServiceFactory::createMeasurementPayloadProvider(dirname(__DIR__));
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

        $minCount = 6;
        $maxCount = 30;
        $count = request_int([$body, $_GET], 'count', 10);
        $query = request_normalize_setup_query(request_text([$body, $_GET], 'query', '', 80));

        if ($count < $minCount || $count > $maxCount) {
            http_response_code(400);
            echo json_encode([
                'error' => true,
                'message' => "Nombre d'images invalide (min {$minCount}, max {$maxCount})",
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return;
        }

        $queryError = request_validate_setup_query($query);
        if ($queryError !== null) {
            http_response_code(400);
            echo json_encode([
                'error' => true,
                'message' => $queryError,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return;
        }

        $rateLimitError = setup_rate_limit_guard();
        if ($rateLimitError !== null) {
            http_response_code($rateLimitError['status']);
            echo json_encode([
                'error' => true,
                'message' => $rateLimitError['message'],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return;
        }

        try {
            $result = run_setup_non_interactive($count, $query);
        } catch (\Throwable $error) {
            $mappedError = setup_map_unsplash_error($error);
            if ($mappedError !== null) {
                http_response_code($mappedError['status']);
                echo json_encode([
                    'error' => true,
                    'message' => $mappedError['message'],
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                return;
            }

            throw $error;
        }

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

    $runJsonUrl = $baseUrlPath . '/public/analyse.php?run=1&format=json';
    $setupJsonUrl = $baseUrlPath . '/public/analyse.php?setup=1&format=json';
    $resultsUrl = $baseUrlPath . '/public/results';
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
