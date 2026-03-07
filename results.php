<?php
/**
 * Affichage visuel des résultats de comparaison RGB
 *
 * @author  Margot Hourdillé
 * @project RGBMatch — Challenge Firstruner
 * @version 1.0.0
 * @date    2026-03-06
 * @update  2026-03-06
 */

require_once __DIR__ . '/app/bootstrap.php';

use RGBMatch\Application\ResultsPageRenderer;
use RGBMatch\Singletons\ConfigurationManager;


$baseUrlPath = app_base_url_path();

try {
    // Configuration
    $config = ConfigurationManager::getInstance();
    $originPath = $config->get('paths.origin');
    $testDir = $config->get('paths.test');
    
    // Vérifications
    if (!file_exists($originPath)) {
        throw new RuntimeException("Lancez setup.php d'abord.");
    }
    
    $testImagePaths = glob($testDir . '/*.jpg');
    if (count($testImagePaths) < 3) {
        throw new RuntimeException("Pas assez d'images. Lancez setup.php d'abord.");
    }
    
    // Construction des dépendances
    [$imageDataBuilder, $comparisonResultBuilder, $ranker] = app_create_image_services();
    
    // Analyse
    $originImage = $imageDataBuilder->build($originPath);

    // Comparaison en streaming: on ne conserve que le Top 3
    $topResults = [];
    foreach ($testImagePaths as $testPath) {
        $testImage = null;
        try {
            $testImage = $imageDataBuilder->build($testPath);
            $result = $comparisonResultBuilder->build($originImage, $testImage);
            $topResults = $ranker->pushTopN($topResults, $result, 3);
        } catch (Exception $e) {
            // Ignore cette image et continue
        } finally {
            if ($testImage !== null) {
                unset($testImage);
            }
            gc_collect_cycles();
        }
    }

    // Assure l'ordre final
    $topResults = $ranker->sortBySimilarityDesc($topResults);

    $renderer = new ResultsPageRenderer();
    echo $renderer->render($baseUrlPath, (string) $originPath, $originImage, $topResults, count($testImagePaths));
    
} catch (Exception $e) {
    die("Erreur: {$e->getMessage()}");
}

