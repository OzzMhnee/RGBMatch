<?php
/**
 * Bootstrap applicatif : autoloader et helpers communs.
 *
 * @author  Margot Hourdillé
 * @project RGBMatch — Challenge Firstruner
 * @version 1.0.0
 * @date    2026-03-06
 * @update  2026-03-06
 */

require_once __DIR__ . '/../loader.php';
require_once __DIR__ . '/CSS_loader.php';
require_once __DIR__ . '/JS_loader.php';

use RGBMatch\Application\ServiceFactory;


/**
 * Calcule le chemin base URL du projet, compatible avec:
 * - http://localhost/RGBMatch/results.php
 * - http://localhost/RGBMatch/public/results.php
 * - VirtualHost: http://rgbmatch.test/results.php
 */
function app_base_url_path(): string
{
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    $baseUrlPath = str_replace('\\', '/', rtrim(dirname($scriptName), '/'));

    // Robustesse: en CLI, SCRIPT_NAME peut ressembler à "public/index.php" (sans '/').
    // On normalise pour que la logique de trimming de "/public" reste cohérente.
    if ($baseUrlPath !== '' && $baseUrlPath[0] !== '/') {
        $baseUrlPath = '/' . $baseUrlPath;
    }

    if ($baseUrlPath === '/' || $baseUrlPath === '.') {
        $baseUrlPath = '';
    }

    // Si on passe par /public, on ancre à la racine du projet.
    if ($baseUrlPath !== '' && substr($baseUrlPath, -7) === '/public') {
        $baseUrlPath = substr($baseUrlPath, 0, -7);
    }

    return $baseUrlPath;
}

/**
 * Construit les dépendances pour l'analyse/comparaison.
 *
 * @param array{sampleRate?:int, maxDimension?:int} $analyzerOptions
 * @return array<int,mixed>
 */
function app_create_image_services(array $analyzerOptions = []): array
{
    return ServiceFactory::createImageServices($analyzerOptions);
}
