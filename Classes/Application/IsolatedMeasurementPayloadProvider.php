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
use RGBMatch\Api\RgbImageAnalyzer;
use RGBMatch\Interfaces\IJsonFileStore;
use RGBMatch\Interfaces\IMeasurementWorkerRunner;
use RGBMatch\Singletons\ConfigurationManager;

final class IsolatedMeasurementPayloadProvider
{
    /** @var string */
    private $projectRoot;

    /** @var IMeasurementWorkerRunner */
    private $workerRunner;

    /** @var IJsonFileStore */
    private $jsonStore;

    public function __construct(string $projectRoot, IMeasurementWorkerRunner $workerRunner, IJsonFileStore $jsonStore)
    {
        $this->projectRoot = rtrim($projectRoot, DIRECTORY_SEPARATOR);
        $this->workerRunner = $workerRunner;
        $this->jsonStore = $jsonStore;
    }

    /**
     * Retourne le payload de mesures partagé entre CLI et web.
     * Si le cache courant est valide, il est réutilisé ; sinon un worker
        * HTTP local isolé régénère le payload.
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
            $cached = $this->jsonStore->read($cacheFile);
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
            $payload = $this->workerRunner->run($this->projectRoot, $resolved);
        } catch (\Throwable $workerError) {
            // Fallback robuste : si le worker HTTP local n'est pas disponible,
            // on rebascule sur le calcul in-process plutôt que de casser CLI/web.
            $payload = $this->buildPayload($resolved);
        }
        if (!isset($payload['meta']['signature']) || (string) $payload['meta']['signature'] !== $signature) {
            throw new RuntimeException('Le worker a retourné une signature de mesures inattendue.');
        }

        // Le cache est un bonus de performance. Un problème de droits d'écriture
        // ne doit jamais casser l'endpoint web.
        try {
            $this->jsonStore->write($cacheFile, [
                'meta' => ['signature' => $signature],
                'payload' => $payload,
            ]);
        } catch (\Throwable $error) {
            // Le cache reste opportuniste : un echec d'ecriture n'est pas bloquant.
        }

        return $payload;
    }

    /**
     * Construit le payload complet dans le process courant.
    * Utilisé uniquement par le worker HTTP local ou le fallback in-process.
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

        $imageLoader = ServiceFactory::createImageLoader();
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