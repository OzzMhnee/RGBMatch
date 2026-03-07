<?php
/**
 * Produit un payload de mesures isolé du process hôte et partagé
 * entre la CLI et le web via un cache JSON commun.
 *
 * Objectif : éliminer les écarts dus à l'état mémoire du script appelant
 * (chapitres CLI, rendu HTML/JS, buffers de sortie, etc.).
 *
 * @author  Margot Hourdillé
 * @project RGBMatch — Challenge Firstruner
 * @version 1.0.0
 * @date    2026-03-07
 * @update  2026-03-07
 */

namespace RGBMatch\Application;

use RuntimeException;
use RGBMatch\Api\GdImageLoader;
use RGBMatch\Api\RgbImageAnalyzer;
use RGBMatch\Singletons\ConfigurationManager;

final class IsolatedMeasurementPayloadProvider
{
    /** @var string */
    private $projectRoot;

    public function __construct(string $projectRoot)
    {
        $this->projectRoot = rtrim($projectRoot, DIRECTORY_SEPARATOR);
    }

    /**
     * Retourne le payload de mesures partagé entre CLI et web.
     * Si le cache courant est valide, il est réutilisé ; sinon un worker
     * PHP isolé régénère le payload.
     *
     * @param array{sampleRate?:int,maxDimension?:int,permCount?:int} $options
     * @return array<string,mixed>
     */
    public function getPayload(array $options = []): array
    {
        $resolved = self::resolveOptions($options);
        $signature = $this->computeSignature($resolved);
        $cacheFile = $this->getCacheFile();

        if (is_file($cacheFile)) {
            $json = file_get_contents($cacheFile);
            $cached = is_string($json) ? json_decode($json, true) : null;
            if (
                is_array($cached)
                && isset($cached['meta']['signature'])
                && (string) $cached['meta']['signature'] === $signature
                && isset($cached['payload'])
                && is_array($cached['payload'])
            ) {
                return $cached['payload'];
            }
        }

        try {
            $payload = $this->runWorkerAndDecode();
        } catch (\Throwable $workerError) {
            // Fallback robuste pour le web : certains environnements Apache/WAMP
            // n'autorisent pas exec(), ou exposent un PHP_BINARY non exécutable.
            // On rebascule alors sur le calcul in-process plutôt que de renvoyer HTTP 500.
            $payload = $this->buildPayload($resolved);
        }
        if (!isset($payload['meta']['signature']) || (string) $payload['meta']['signature'] !== $signature) {
            throw new RuntimeException('Le worker a retourné une signature de mesures inattendue.');
        }

        $encoded = json_encode(
            ['meta' => ['signature' => $signature], 'payload' => $payload],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT
        );
        if (!is_string($encoded)) {
            throw new RuntimeException('Impossible de sérialiser le cache de mesures.');
        }
        // Le cache est un bonus de performance. Un problème de droits d'écriture
        // ne doit jamais casser l'endpoint web.
        @file_put_contents($cacheFile, $encoded);

        return $payload;
    }

    /**
     * Construit le payload complet dans le process courant.
     * Utilisé uniquement par le worker isolé.
     *
     * @param array{sampleRate?:int,maxDimension?:int,permCount?:int} $options
     * @return array<string,mixed>
     */
    public function buildPayload(array $options = []): array
    {
        $resolved = self::resolveOptions($options);
        $sampleRate = $resolved['sampleRate'];
        $maxDimension = $resolved['maxDimension'];
        $permCount = $resolved['permCount'];

        gc_collect_cycles();
        if (function_exists('gc_mem_caches')) {
            gc_mem_caches();
        }

        $config = ConfigurationManager::getInstance();
        $originPath = (string) $config->get('paths.origin');
        $testDir = (string) $config->get('paths.test');

        if (!file_exists($originPath)) {
            throw new RuntimeException("Image d'origine introuvable. Lancez setup.php d'abord.");
        }

        $testImagePaths = glob($testDir . '/*.jpg');
        if (!is_array($testImagePaths) || count($testImagePaths) < 6) {
            throw new RuntimeException("Pas assez d'images de test (min 6). Lancez setup.php d'abord.");
        }
        sort($testImagePaths, SORT_STRING);

        $perImage = [];
        $maxAbsUsed = 1;
        $maxAbsAlloc = 1;
        $maxAbsPeak = 1;

        $imageLoader = new GdImageLoader();
        $analyzer = new RgbImageAnalyzer($imageLoader, [
            'sampleRate' => $sampleRate,
            'maxDimension' => $maxDimension,
        ]);
        [$imageDataBuilder, $comparisonResultBuilder, $ranker] = ServiceFactory::createImageServicesFromAnalyzer($analyzer);

        $orchestrator = new AnalysisOrchestrator($analyzer, $imageDataBuilder, $comparisonResultBuilder, $ranker, 3);
        $originImage = $orchestrator->buildOriginImage($originPath);

        // Warm-up volontaire : supprime les allocations de premier usage
        // (autoload, premiers appels métier, structures internes) des mesures réelles.
        $warmupPath = $testImagePaths[0];
        $orchestrator->processAll($originImage, [$warmupPath], 0, null, null);
        gc_collect_cycles();
        if (function_exists('gc_mem_caches')) {
            gc_mem_caches();
        }

        $onImage = static function (array $report) use (&$perImage, &$maxAbsUsed, &$maxAbsAlloc, &$maxAbsPeak): void {
            foreach ($report['steps'] as $st) {
                $maxAbsUsed  = max($maxAbsUsed, abs((int) $st['deltaUsed']));
                $maxAbsAlloc = max($maxAbsAlloc, abs((int) $st['deltaAlloc']));
                $maxAbsPeak  = max($maxAbsPeak, abs((int) $st['deltaPeak']));
            }
            $perImage[] = $report;
        };

        $allResults = $orchestrator->processAll($originImage, $testImagePaths, $permCount, $onImage, $onImage);
        $sortedTop = $ranker->sortBySimilarityDesc($allResults['topResults']);

        $finalResults = [];
        foreach ($sortedTop as $result) {
            if (is_object($result) && method_exists($result, 'toArray')) {
                $finalResults[] = $result->toArray();
            }
        }

        return [
            'meta' => [
                'signature' => $this->computeSignature($resolved),
                'sampleRate' => $sampleRate,
                'maxDimension' => $maxDimension,
                'permCount' => $permCount,
            ],
            'images' => $perImage,
            'maxAbs' => [
                'used' => (int) $maxAbsUsed,
                'alloc' => (int) $maxAbsAlloc,
                'peak' => (int) $maxAbsPeak,
            ],
            'processedCount' => (int) $allResults['processedCount'],
            'skippedCount' => (int) $allResults['skippedCount'],
            'topResults' => $finalResults,
        ];
    }

    /**
     * @param array{sampleRate:int,maxDimension:int,permCount:int} $options
     */
    private function computeSignature(array $options): string
    {
        $config = ConfigurationManager::getInstance();
        $originPath = (string) $config->get('paths.origin');
        $testDir = (string) $config->get('paths.test');
        $testImagePaths = glob($testDir . '/*.jpg');
        if (!is_array($testImagePaths)) {
            $testImagePaths = [];
        }
        sort($testImagePaths, SORT_STRING);

        $files = [
            $originPath,
            $this->projectRoot . '/Classes/Application/AnalysisOrchestrator.php',
            $this->projectRoot . '/Classes/Api/RgbImageAnalyzer.php',
            $this->projectRoot . '/Classes/Builders/ComparisonResultBuilder.php',
            $this->projectRoot . '/Classes/Application/ComparisonResultRanker.php',
        ];
        $files = array_merge($files, $testImagePaths);

        $fingerprints = [];
        foreach ($files as $file) {
            $fingerprints[] = [
                'path' => (string) $file,
                'size' => is_file($file) ? (int) filesize($file) : -1,
                'mtime' => is_file($file) ? (int) filemtime($file) : -1,
            ];
        }

        return sha1(json_encode([
            'options' => $options,
            'files' => $fingerprints,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function getCacheFile(): string
    {
        return $this->projectRoot . '/storage/results/pipeline-measurements.json';
    }

    /**
     * @return array<string,mixed>
     */
    private function runWorkerAndDecode(): array
    {
        if (!function_exists('exec')) {
            throw new RuntimeException('exec() indisponible pour lancer le worker de mesures.');
        }

        $php = PHP_BINARY;
        if (!is_string($php) || $php === '' || !is_file($php)) {
            $php = $this->guessPhpBinary();
        }
        if (!is_string($php) || $php === '' || !is_file($php)) {
            throw new RuntimeException('PHP executable introuvable pour lancer le worker de mesures.');
        }

        $worker = $this->projectRoot . '/app/measurement_worker.php';
        if (!is_file($worker)) {
            throw new RuntimeException('Worker de mesures introuvable.');
        }

        $command = $this->quoteArg($php) . ' ' . $this->quoteArg($worker);
        $output = [];
        $exitCode = 0;
        exec($command, $output, $exitCode);

        if ($exitCode !== 0) {
            throw new RuntimeException('Le worker de mesures a échoué : ' . implode("\n", $output));
        }

        $json = implode("\n", $output);
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('JSON invalide retourné par le worker de mesures.');
        }

        return $decoded;
    }

    /**
     * Tente de retrouver un exécutable PHP utilisable quand PHP_BINARY
     * pointe vers une DLL Apache ou une valeur non exécutable.
     */
    private function guessPhpBinary(): string
    {
        $candidates = [];

        if (defined('PHP_BINDIR')) {
            $candidates[] = rtrim((string) PHP_BINDIR, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'php.exe';
            $candidates[] = rtrim((string) PHP_BINDIR, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'php';
        }

        if (isset($_SERVER['PATH']) && is_string($_SERVER['PATH'])) {
            foreach (explode(PATH_SEPARATOR, $_SERVER['PATH']) as $dir) {
                $dir = trim($dir);
                if ($dir === '') {
                    continue;
                }
                $candidates[] = rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'php.exe';
                $candidates[] = rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'php';
            }
        }

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return '';
    }

    private function quoteArg(string $value): string
    {
        return '"' . str_replace('"', '\\"', $value) . '"';
    }

    /**
     * @param array{sampleRate?:int,maxDimension?:int,permCount?:int} $options
     * @return array{sampleRate:int,maxDimension:int,permCount:int}
     */
    private static function resolveOptions(array $options): array
    {
        return [
            'sampleRate' => isset($options['sampleRate']) ? max(1, (int) $options['sampleRate']) : 10,
            'maxDimension' => isset($options['maxDimension']) ? max(0, (int) $options['maxDimension']) : 0,
            'permCount' => isset($options['permCount']) ? max(0, (int) $options['permCount']) : 6,
        ];
    }
}