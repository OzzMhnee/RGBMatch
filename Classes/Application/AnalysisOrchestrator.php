<?php
/**
 * Orchestrateur d'analyse : encapsule le workflow complet
 * « charger config → scanner → analyser chaque image → construire résultat → top N → cleanup ».
 *
 * Élimine la duplication majeure entre index.php (CLI) et public/index.php (JSON).
 *
 * Design choice :
 * - Pas de C prefix (ce n'est pas un Value Object).
 * - Mesures directes (avant/après action) pour éviter toute référence extra.
 * - processAll() est le point d'entrée unique, partagé par CLI et web.
 * - Accepte des callbacks par image pour collecter les données de progression.
 *
 * @author  Margot Hourdillé
 * @project RGBMatch — Challenge Firstruner
 * @version 1.0.0
 * @date    2026-03-06
 * @update  2026-03-06
 */

namespace RGBMatch\Application;

use RGBMatch\Enumerations\EImageType;
use RGBMatch\Interfaces\IComparisonResultBuilder;
use RGBMatch\Interfaces\IComparisonResultRanker;
use RGBMatch\Interfaces\IImageDataBuilder;
use RGBMatch\Interfaces\IInstrumentedImageAnalyzer;
use RGBMatch\Metier\CImageData;

final class AnalysisOrchestrator
{
    /** @var IInstrumentedImageAnalyzer */
    private $analyzer;

    /** @var IImageDataBuilder */
    private $imageDataBuilder;

    /** @var IComparisonResultBuilder */
    private $comparisonResultBuilder;

    /** @var IComparisonResultRanker */
    private $ranker;

    /** @var int */
    private $topN;

    public function __construct(
        IInstrumentedImageAnalyzer $analyzer,
        IImageDataBuilder $imageDataBuilder,
        IComparisonResultBuilder $comparisonResultBuilder,
        IComparisonResultRanker $ranker,
        int $topN = 3
    ) {
        $this->analyzer = $analyzer;
        $this->imageDataBuilder = $imageDataBuilder;
        $this->comparisonResultBuilder = $comparisonResultBuilder;
        $this->ranker = $ranker;
        $this->topN = $topN;
    }

    /**
     * Analyse l'image d'origine et retourne son CImageData.
     */
    public function buildOriginImage(string $originPath): CImageData
    {
        return $this->imageDataBuilder->build($originPath);
    }

    // ═══════════════════════════════════════════════════════════════════════
    //  Constante publique : les 6 permutations de nettoyage (chapitre 9).
    //  Partagée entre CLI et web pour garantir l'identité des mesures.
    // ═══════════════════════════════════════════════════════════════════════

    /** @var array{label:string, order:string[]}[] */
    public static $permutations = [
        ['label' => 'Free GD → unset → GC',  'order' => ['freeGd', 'unset', 'gc']],
        ['label' => 'Free GD → GC → unset',  'order' => ['freeGd', 'gc', 'unset']],
        ['label' => 'unset → GC → Free GD',  'order' => ['unset', 'gc', 'freeGd']],
        ['label' => 'unset → Free GD → GC',  'order' => ['unset', 'freeGd', 'gc']],
        ['label' => 'GC → Free GD → unset',  'order' => ['gc', 'freeGd', 'unset']],
        ['label' => 'GC → unset → Free GD',  'order' => ['gc', 'unset', 'freeGd']],
    ];

    // ═══════════════════════════════════════════════════════════════════════
    //  API publique
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Point d'entrée principal — traite toutes les images en deux phases.
     *
     * Phase 1 (ch.9 pédagogique) : les $permCount premières images utilisent
     *   les permutations de nettoyage ; le GD reste vivant pendant Build/Ranking
     *   pour illustrer l'impact de l'ordre sur la RAM.
     *
     * Phase 2 (pipeline standard) : images restantes avec l'ordre optimal
     *   Free GD → Build → unset → GC.
     *
     * Les deux phases utilisent des mesures directes (pas de closure InstrumentedStep)
     * pour éviter toute référence extra qui fausserait le delta « unset ».
     *
     * @param CImageData    $originImage
     * @param string[]      $testImagePaths
     * @param int           $permCount            Nb d'images ch.9 (0 = tout en standard)
     * @param callable|null $onPermutationImage   Callback(array $report) pour ch.9
     * @param callable|null $onStandardImage      Callback(array $report) pour le reste
     *
     * @return array{topResults:array, processedCount:int, skippedCount:int}
     */
    public function processAll(
        CImageData $originImage,
        array $testImagePaths,
        int $permCount = 6,
        ?callable $onPermutationImage = null,
        ?callable $onStandardImage = null
    ): array {
        // Normalisation GC avant toute mesure : réduit l'impact de l'état PHP
        // accumulé (CLI : chapitres précédents / Web : overhead HTTP) pour que
        // les deltas du même pipeline soient comparables entre les deux contextes.
        gc_collect_cycles();
        if (function_exists('gc_mem_caches')) {
            gc_mem_caches();
        }

        $topResults    = [];
        $processedCount = 0;
        $skippedCount   = 0;

        $normalized  = array_values($testImagePaths);
        $perms       = self::$permutations;
        $permTotal   = count($perms);

        /** @var string $testPath */
        foreach ($normalized as $idx => $testPath) {
            if ($idx < $permCount) {
                $perm   = $perms[$idx % $permTotal];
                $report = $this->processOneImagePermutation(
                    $originImage, $testPath, $idx + 1, $perm,
                    $topResults, $processedCount, $skippedCount
                );
                if ($report !== null && $onPermutationImage !== null) {
                    $onPermutationImage($report);
                }
            } else {
                $report = $this->processOneImageStandard(
                    $originImage, $testPath, $idx + 1,
                    $topResults, $processedCount, $skippedCount
                );
                if ($report !== null && $onStandardImage !== null) {
                    $onStandardImage($report);
                }
            }
        }

        return compact('topResults', 'processedCount', 'skippedCount');
    }

    /**
     * Compat. ascendante : toutes les images avec le pipeline standard.
     *
     * @param CImageData    $originImage
     * @param string[]      $testImagePaths
     * @param callable|null $onImageProcessed
     *
     * @return array{topResults:array, processedCount:int, skippedCount:int}
     */
    public function processTestImages(
        CImageData $originImage,
        array $testImagePaths,
        ?callable $onImageProcessed = null
    ): array {
        return $this->processAll($originImage, $testImagePaths, 0, null, $onImageProcessed);
    }

    // ═══════════════════════════════════════════════════════════════════════
    //  Pipelines privés
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Pipeline ch.9 : GD reste vivant pendant Build/Result/Ranking,
     * puis nettoyage selon l'ordre de la permutation courante.
     *
     * Mesures directes uniquement (pas de closure) pour éviter toute
     * référence extra qui fausserait le delta « unset ».
     *
     * @param array  $perm          ['label'=>string, 'order'=>string[]]
     * @param array  &$topResults   Tableau Top N partagé (accumulé entre images)
     */
    private function processOneImagePermutation(
        CImageData $originImage,
        string $testPath,
        int $displayIndex,
        array $perm,
        array &$topResults,
        int &$processedCount,
        int &$skippedCount
    ): ?array {
        $testPath      = (string) $testPath;
        $filename      = basename($testPath);
        $fileSizeBytes = is_file($testPath) ? (int) filesize($testPath) : 0;
        $steps         = [];
        $cleanupDeltas = ['freeGd' => 0, 'unset' => 0, 'gc' => 0];

        // Normalisation par image : libère les pages PHP acculées par la callback/étapes
        // précédentes pour que le baseline alloc soit comparable entre CLI et web.
        gc_collect_cycles();
        if (function_exists('gc_mem_caches')) {
            gc_mem_caches();
        }

        $type = file_exists($testPath) ? EImageType::fromPath($testPath) : null;

        if ($type === null) {
            $skippedCount++;
            return null;
        }

        // ── 2-4. Load GD + Downscale + Analyse RGB (GD gardé) ──
        $loaded = $this->runLoadAndAnalyze($testPath);
        if ($loaded === null) {
            $skippedCount++;
            return null;
        }
        $rgb   = $loaded['rgb'];
        $gd    = $loaded['gd'];
        $steps = array_merge($steps, $loaded['steps']);
        unset($loaded); // libère $loaded['gd'] → $gd est la seule référence au GdImage

        // ── 5. Build CImageData (GD encore en vie — mesure directe) ──
        [$uB, $aB, $pB, $t0] = self::snap4();
        $testImage = new CImageData($testPath, $rgb, $type);
        [$uA, $aA, $pA] = self::snap3();
        $steps[] = self::buildUsageStep('Build CImageData', 'Objet léger (chemin + RGB)', $uB, $aB, $pB, $uA, $aA, $pA, self::ms($t0));

        // ── 6. Build Result (GD encore en vie) ──
        [$uB, $aB, $pB, $t0] = self::snap4();
        $result = $this->comparisonResultBuilder->build($originImage, $testImage);
        [$uA, $aA, $pA] = self::snap3();
        $steps[] = self::buildUsageStep('Build Result', 'Calcule score + différences', $uB, $aB, $pB, $uA, $aA, $pA, self::ms($t0));
        $similarityScore = (float) $result->getSimilarity();

        // ── 7. Ranking (GD encore en vie) ──
        [$uB, $aB, $pB, $t0] = self::snap4();
        $topResults = $this->ranker->pushTopN($topResults, $result, $this->topN);
        [$uA, $aA, $pA] = self::snap3();
        $steps[] = self::buildUsageStep(
            'Ranking Top' . $this->topN,
            'Garde seulement les ' . $this->topN . ' meilleurs',
            $uB, $aB, $pB, $uA, $aA, $pA, self::ms($t0)
        );

        // ── 8. Cleanup selon la permutation ──
        // Capture les valeurs RGB avant que « unset » ne puisse annuler $rgb.
        $rgbValues = self::captureRgb($rgb);

        foreach ($perm['order'] as $action) {
            switch ($action) {
                case 'freeGd':
                    [$uB, $aB, $pB, $t0] = self::snap4();
                    $this->analyzer->freeGd($gd);
                    $gd = null;
                    [$uA, $aA, $pA] = self::snap3();
                    $st = self::buildUsageStep('Free GD', 'imagedestroy() — libère le bitmap', $uB, $aB, $pB, $uA, $aA, $pA, self::ms($t0));
                    $steps[] = $st;
                    $cleanupDeltas['freeGd'] = $st['deltaUsed'];
                    break;

                case 'unset':
                    [$uB, $aB, $pB, $t0] = self::snap4();
                    $testImage = null;
                    $result    = null;
                    $rgb       = null;
                    [$uA, $aA, $pA] = self::snap3();
                    $st = self::buildUsageStep('unset', 'Retire les références des objets légers', $uB, $aB, $pB, $uA, $aA, $pA, self::ms($t0));
                    $steps[] = $st;
                    $cleanupDeltas['unset'] = $st['deltaUsed'];
                    break;

                case 'gc':
                    [$uB, $aB, $pB, $t0] = self::snap4();
                    gc_collect_cycles();
                    if (function_exists('gc_mem_caches')) {
                        gc_mem_caches();
                    }
                    [$uA, $aA, $pA] = self::snap3();
                    $st = self::buildUsageStep('GC', 'gc_collect_cycles() — collecte les cycles orphelins', $uB, $aB, $pB, $uA, $aA, $pA, self::ms($t0));
                    $steps[] = $st;
                    $cleanupDeltas['gc'] = $st['deltaUsed'];
                    break;
            }
        }

        $processedCount++;
        $top3Simple = self::buildTop3Simple($this->ranker->sortBySimilarityDesc($topResults));

        return [
            'index'         => $displayIndex,
            'filename'      => $filename,
            'fileSizeBytes' => $fileSizeBytes,
            'steps'         => $steps,
            'similarity'    => $similarityScore,
            'rgb'           => $rgbValues,
            'top3'          => $top3Simple,
            'perm'          => $perm,
            'cleanupDeltas' => $cleanupDeltas,
        ];
    }

    /**
     * Pipeline standard : Free GD en premier (avant Build),
     * puis unset → GC.  Ordre optimal pour minimiser le pic RAM.
     *
     * Mesures directes uniquement pour éviter toute référence extra.
     *
     * @param array &$topResults  Tableau Top N partagé
     */
    private function processOneImageStandard(
        CImageData $originImage,
        string $testPath,
        int $displayIndex,
        array &$topResults,
        int &$processedCount,
        int &$skippedCount
    ): ?array {
        $testPath      = (string) $testPath;
        $filename      = basename($testPath);
        $fileSizeBytes = is_file($testPath) ? (int) filesize($testPath) : 0;
        $steps         = [];

        // Normalisation par image : libère les pages PHP acculées par la callback/étapes
        // précédentes pour que le baseline alloc soit comparable entre CLI et web.
        gc_collect_cycles();
        if (function_exists('gc_mem_caches')) {
            gc_mem_caches();
        }

        $type = file_exists($testPath) ? EImageType::fromPath($testPath) : null;

        if ($type === null) {
            $skippedCount++;
            return null;
        }

        // ── 2-4. Load GD + Downscale + Analyse RGB (GD gardé) ──
        $loaded = $this->runLoadAndAnalyze($testPath);
        if ($loaded === null) {
            $skippedCount++;
            return null;
        }
        $rgb   = $loaded['rgb'];
        $gd    = $loaded['gd'];
        $steps = array_merge($steps, $loaded['steps']);
        unset($loaded); // libère $loaded['gd'] → $gd est la seule référence au GdImage

        // ── 5. Free GD — AVANT Build (pipeline standard) ──
        [$uB, $aB, $pB, $t0] = self::snap4();
        $this->analyzer->freeGd($gd);
        $gd = null;
        [$uA, $aA, $pA] = self::snap3();
        $steps[] = self::buildUsageStep(
            'Free GD',
            "Libère l'image GD (pixels → bitmap supprimé)",
            $uB, $aB, $pB, $uA, $aA, $pA, self::ms($t0)
        );

        // ── 6. Build CImageData ──
        [$uB, $aB, $pB, $t0] = self::snap4();
        $testImage = new CImageData($testPath, $rgb, $type);
        [$uA, $aA, $pA] = self::snap3();
        $steps[] = self::buildUsageStep('Build CImageData', 'Objet léger (chemin + RGB)', $uB, $aB, $pB, $uA, $aA, $pA, self::ms($t0));

        // ── 7. Build Result ──
        [$uB, $aB, $pB, $t0] = self::snap4();
        /** @var CImageData $originImage */
        $result = $this->comparisonResultBuilder->build($originImage, $testImage);
        [$uA, $aA, $pA] = self::snap3();
        $steps[] = self::buildUsageStep('Build Result', 'Calcule score + différences', $uB, $aB, $pB, $uA, $aA, $pA, self::ms($t0));
        $similarityScore = (float) $result->getSimilarity();

        // ── 8. Ranking ──
        [$uB, $aB, $pB, $t0] = self::snap4();
        $topResults = $this->ranker->pushTopN($topResults, $result, $this->topN);
        [$uA, $aA, $pA] = self::snap3();
        $steps[] = self::buildUsageStep(
            'Ranking Top' . $this->topN,
            'Garde seulement les ' . $this->topN . ' meilleurs',
            $uB, $aB, $pB, $uA, $aA, $pA, self::ms($t0)
        );

        // ── 9. unset ──
        $rgbValues = self::captureRgb($rgb);
        [$uB, $aB, $pB, $t0] = self::snap4();
        $testImage = null;
        $result    = null;
        $rgb       = null;
        [$uA, $aA, $pA] = self::snap3();
        $steps[] = self::buildUsageStep(
            'unset',
            'Retire les références (refcount → 0 si pas dans Top' . $this->topN . ')',
            $uB, $aB, $pB, $uA, $aA, $pA, self::ms($t0)
        );

        // ── 10. GC ──
        [$uB, $aB, $pB, $t0] = self::snap4();
        gc_collect_cycles();
        if (function_exists('gc_mem_caches')) {
            gc_mem_caches();
        }
        [$uA, $aA, $pA] = self::snap3();
        $steps[] = self::buildUsageStep(
            'GC',
            'gc_collect_cycles() : collecte les cycles orphelins éventuels',
            $uB, $aB, $pB, $uA, $aA, $pA, self::ms($t0)
        );

        $processedCount++;
        $top3Simple = self::buildTop3Simple($this->ranker->sortBySimilarityDesc($topResults));

        return [
            'index'         => $displayIndex,
            'filename'      => $filename,
            'fileSizeBytes' => $fileSizeBytes,
            'steps'         => $steps,
            'similarity'    => $similarityScore,
            'rgb'           => $rgbValues,
            'top3'          => $top3Simple,
        ];
    }

    // ═══════════════════════════════════════════════════════════════════════
    //  Helpers partagés
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Exécute analyzeWithStatsKeepGd et construit les 3 steps Load/Downscale/Analyse.
     * Retourne null si l'image est introuvable ou non chargeble.
     *
     * @return array{rgb:\RGBMatch\Metier\CRgbPercentage, gd:mixed, steps:array}|null
     */
    private function runLoadAndAnalyze(string $testPath): ?array
    {
        try {
            $out = $this->analyzer->analyzeWithStatsKeepGd($testPath);
        } catch (\RuntimeException $e) {
            return null;
        }
        $rgb   = $out['rgb'];
        $stats = $out['stats'];
        $snaps = $stats['snaps'];
        $gd    = $out['gd'];
        unset($out);

        $steps = [
            self::buildSnapStep(
                'Load GD', 'Décompresse le fichier en pixels (pic RAM)',
                $snaps['beforeLoad'], $snaps['afterLoad'], $stats['loadSeconds']
            ),
            self::buildSnapStep(
                'Downscale',
                !empty($stats['didDownscale']) ? 'Réduit la taille de travail' : 'Pas de resize (option désactivée)',
                $snaps['afterLoad'], $snaps['afterDownscale'], $stats['downscaleSeconds']
            ),
            self::buildSnapStep(
                'Analyse RGB', 'Lit des pixels (CPU) avec échantillonnage',
                $snaps['afterDownscale'], $snaps['afterCalc'], $stats['calcSeconds']
            ),
        ];

        return compact('rgb', 'gd', 'steps');
    }

    /** Capture un snapshot à 4 valeurs : used, alloc, peak, timestamp. */
    private static function snap4(): array
    {
        return [
            memory_get_usage(false),
            memory_get_usage(true),
            memory_get_peak_usage(true),
            microtime(true),
        ];
    }

    /** Capture un snapshot à 3 valeurs : used, alloc, peak. */
    private static function snap3(): array
    {
        return [
            memory_get_usage(false),
            memory_get_usage(true),
            memory_get_peak_usage(true),
        ];
    }

    /** Durée en ms depuis $t0. */
    private static function ms(float $t0): int
    {
        return (int) round((microtime(true) - $t0) * 1000);
    }

    /**
     * Capture les valeurs RGB avant une étape unset potentielle.
     *
     * @return array{r:float, g:float, b:float}
     */
    private static function captureRgb(\RGBMatch\Metier\CRgbPercentage $rgb): array
    {
        return [
            'r' => round($rgb->getRed(), 2),
            'g' => round($rgb->getGreen(), 2),
            'b' => round($rgb->getBlue(), 2),
        ];
    }

    /**
     * Construit le tableau top3Simple à partir d'une liste triée de résultats.
     *
     * @param  array $sortedResults
     * @return array{filename:string, similarity:float}[]
     */
    private static function buildTop3Simple(array $sortedResults): array
    {
        $top3 = [];
        foreach ($sortedResults as $r) {
            if (!is_object($r) || !method_exists($r, 'toArray')) {
                continue;
            }
            $arr    = $r->toArray();
            $top3[] = [
                'filename'   => (string) ($arr['filename'] ?? ''),
                'similarity' => (float) ($arr['similarity'] ?? 0),
            ];
        }
        return $top3;
    }

    /** Construit un step à partir de deux snapshots (format analyzeWithStats). */
    private static function buildSnapStep(
        string $label,
        string $explain,
        array $snapBefore,
        array $snapAfter,
        float $seconds
    ): array {
        return [
            'step'       => $label,
            'explain'    => $explain,
            'deltaUsed'  => (int) $snapAfter['used']      - (int) $snapBefore['used'],
            'deltaAlloc' => (int) $snapAfter['alloc']      - (int) $snapBefore['alloc'],
            'deltaPeak'  => (int) $snapAfter['peakAlloc']  - (int) $snapBefore['peakAlloc'],
            'elapsedMs'  => (int) round($seconds * 1000),
        ];
    }

    /** Construit un step à partir de valeurs scalaires (mesures directes). */
    private static function buildUsageStep(
        string $label,
        string $explain,
        int $uB,
        int $aB,
        int $pB,
        int $uA,
        int $aA,
        int $pA,
        int $elapsedMs
    ): array {
        return [
            'step'       => $label,
            'explain'    => $explain,
            'deltaUsed'  => $uA - $uB,
            'deltaAlloc' => $aA - $aB,
            'deltaPeak'  => $pA - $pB,
            'elapsedMs'  => $elapsedMs,
        ];
    }
}
