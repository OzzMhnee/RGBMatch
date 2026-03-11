<?php
/**
 * Page d'initialisation (setup) — téléchargement Unsplash + aperçu images.
 *
 * © 2026 Tous droits réservés.
 *
 * @author  Margot Hourdillé
 * @project RGBMatch — Challenge Firstruner
 * @version 1.0.0
 * @date    2026-03-11
 */

require_once __DIR__ . '/../app/bootstrap.php';

use RGBMatch\Application\SetupPageRenderer;
use RGBMatch\Singletons\ConfigurationManager;

$baseUrlPath = app_base_url_path();

try {
    $config     = ConfigurationManager::getInstance();
    $originPath = (string) $config->get('paths.origin');
    $testDir    = (string) $config->get('paths.test');

    $originExists = ($originPath !== '' && file_exists($originPath));

    $testImages = [];
    $jpgFiles   = glob($testDir . '/*.jpg');
    if (is_array($jpgFiles)) {
        foreach ($jpgFiles as $path) {
            $testImages[] = basename($path);
        }
        sort($testImages);
    }

    $setupJsonUrl = $baseUrlPath . '/public/analyse.php?setup=1&format=json';

    $renderer = new SetupPageRenderer();
    echo $renderer->render($baseUrlPath, $setupJsonUrl, $originPath, $originExists, $testImages);
} catch (Throwable $e) {
    http_response_code(500);
    $msg = htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    echo "<h1>Erreur</h1><p>{$msg}</p>";
}
