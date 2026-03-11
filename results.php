<?php
/**
 * Affichage visuel des résultats de comparaison RGB.
 *
 * © 2026 Tous droits réservés.
 *
 * @author  Margot Hourdillé
 * @project RGBMatch — Challenge Firstruner
 * @version 2.0.0
 * @date    2026-03-06
 * @update  2026-03-11
 */

require_once __DIR__ . '/app/bootstrap.php';

use RGBMatch\Application\PublicPageLayoutRenderer;
use RGBMatch\Application\ResultsPageRenderer;
use RGBMatch\Singletons\ConfigurationManager;

$baseUrlPath = app_base_url_path();

try {
    $config     = ConfigurationManager::getInstance();
    $originPath = (string) $config->get('paths.origin');
    $testDir    = (string) $config->get('paths.test');

    $setupPageUrl = rtrim($baseUrlPath, '/') . '/public/setup';

    // ── Vérifications avec messages conviviaux ─────────────
    if (!file_exists($originPath)) {
        $layout = new PublicPageLayoutRenderer();
        echo $layout->renderPage(
            $baseUrlPath,
            'RGBMatch — Résultats',
            'results',
            '<div class="page-section"><div class="sec-header reveal reveal-left">'
            . '<span class="sec-arrow">→</span>'
            . '<h2 class="sec-title">Aucun résultat disponible</h2></div>'
            . '<div class="sec-body reveal"><section class="origin-section"><div class="origin-info">'
            . '<div class="note"><strong>Aucune image de référence n\'a été trouvée.</strong><br>'
            . 'Rendez-vous sur la page <a href="' . htmlspecialchars($setupPageUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">Initialisation</a> '
            . 'pour télécharger les images depuis Unsplash.</div>'
            . '<div class="actions" style="margin-top:16px;">'
            . '<a class="btn-primary" href="' . htmlspecialchars($setupPageUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">Initialiser les images</a></div>'
            . '</div></section></div></div>',
            [
                'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap',
                'assets/shared/tokens.css',
                'assets/shared/layout.css',
                'assets/shared/origin.css',
                'assets/shared/buttons.css',
                'assets/shared/footer.css',
            ],
            ['assets/shared/reveal.js']
        );
        return;
    }

    $testImagePaths = glob($testDir . '/*.jpg');
    if (!is_array($testImagePaths) || count($testImagePaths) < 3) {
        $layout = new PublicPageLayoutRenderer();
        $count  = is_array($testImagePaths) ? count($testImagePaths) : 0;
        echo $layout->renderPage(
            $baseUrlPath,
            'RGBMatch — Résultats',
            'results',
            '<div class="page-section"><div class="sec-header reveal reveal-left">'
            . '<span class="sec-arrow">→</span>'
            . '<h2 class="sec-title">Pas assez d\'images</h2></div>'
            . '<div class="sec-body reveal"><section class="origin-section"><div class="origin-info">'
            . '<div class="note"><strong>Seulement ' . $count . ' image(s) de test trouvée(s)</strong> (minimum 3 requises).<br>'
            . 'Rendez-vous sur la page <a href="' . htmlspecialchars($setupPageUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">Initialisation</a> '
            . 'pour télécharger davantage d\'images.</div>'
            . '<div class="actions" style="margin-top:16px;">'
            . '<a class="btn-primary" href="' . htmlspecialchars($setupPageUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">Initialiser les images</a></div>'
            . '</div></section></div></div>',
            [
                'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap',
                'assets/shared/tokens.css',
                'assets/shared/layout.css',
                'assets/shared/origin.css',
                'assets/shared/buttons.css',
                'assets/shared/footer.css',
            ],
            ['assets/shared/reveal.js']
        );
        return;
    }

    // ── Construction des dépendances ───────────────────────
    [$imageDataBuilder, $comparisonResultBuilder, $ranker] = app_create_image_services();

    $originImage = $imageDataBuilder->build($originPath);

    // Comparaison en streaming : on ne conserve que le Top 3
    $topResults = [];
    foreach ($testImagePaths as $testPath) {
        $testImage = null;
        try {
            $testImage = $imageDataBuilder->build($testPath);
            $result    = $comparisonResultBuilder->build($originImage, $testImage);
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

    // Ordre final
    $topResults = $ranker->sortBySimilarityDesc($topResults);

    $renderer = new ResultsPageRenderer();
    echo $renderer->render($baseUrlPath, $originPath, $originImage, $topResults, count($testImagePaths));
} catch (Throwable $e) {
    http_response_code(500);
    $msg = htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    echo "<h1>Erreur</h1><p>{$msg}</p>";
}

