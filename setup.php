<?php
/**
 * Script de setup - Téléchargement des images depuis Unsplash
 * Utilisation du pattern Factory/Builder
 *
 * @author  Margot Hourdillé
 * @project RGBMatch — Challenge Firstruner
 * @version 1.0.0
 * @date    2026-03-06
 * @update  2026-03-06
 */

require_once __DIR__ . '/app/bootstrap.php';

use RGBMatch\Api\UnsplashApiClient;
use RGBMatch\Metier\BytesFormatter;
use RGBMatch\Singletons\ConfigurationManager;


echo "\n";
echo "╔══════════════════════════════════════════════════════╗\n";
echo "║        SETUP - Téléchargement des images             ║\n";
echo "╚══════════════════════════════════════════════════════╝\n\n";
echo "\n";

$setupStart = microtime(true);
$ramStart = memory_get_usage(false);
$peakStart = memory_get_peak_usage(true);

try {
    // Récupération de la configuration via Singleton
    $config = ConfigurationManager::getInstance();
    
    $accessKey = $config->get('unsplash.access_key');
    $apiUrl = $config->get('unsplash.api_url');
    $testDir = $config->get('paths.test');
    $originPath = $config->get('paths.origin');
    
    // Vérification de la clé API
    if (empty($accessKey)) {
        throw new RuntimeException(
            "Clé API Unsplash non configurée. Vérifiez votre fichier .env"
        );
    }
    
    // Initialisation du client API
    $apiClient = new UnsplashApiClient($accessKey, $apiUrl, $testDir);
    $setupService = new \RGBMatch\Application\SetupService($apiClient, $testDir, $originPath);

    $downloadMode = $_ENV['UNSPLASH_DOWNLOAD_MODE'] ?? getenv('UNSPLASH_DOWNLOAD_MODE') ?? 'stream';
    $downloadMode = strtolower(trim((string) $downloadMode));
    if ($downloadMode !== 'memory') {
        $downloadMode = 'stream';
    }
    echo sprintf("[INFO] UNSPLASH_DOWNLOAD_MODE=%s (%s)\n\n", $downloadMode, $downloadMode === 'stream' ? 'RAM minimale' : 'démo: binaire en string');
    
    // Nettoyage des anciennes images
    echo "[INFO] Nettoyage des anciennes images...\n";
    $oldImages = glob($testDir . '/*.jpg');
    foreach ($oldImages as $oldImage) {
        unlink($oldImage);
    }
    if (file_exists($originPath)) {
        unlink($originPath);
    }

    // Choix dynamique dans le terminal
    $minCount = 6;
    $maxCount = 30;
    $defaultCount = 10;
    echo "\nCombien d'images de test télécharger ? [{$defaultCount}] : ";
    $countInput = function_exists('readline') ? readline() : fgets(STDIN);
    $countInput = trim((string) $countInput);
    $count = $defaultCount;
    if ($countInput !== '') {
        if (!ctype_digit($countInput)) {
            throw new RuntimeException("Nombre d'images invalide");
        }
        $count = (int) $countInput;
        if ($count < $minCount || $count > $maxCount) {
            throw new RuntimeException("Nombre d'images invalide (min {$minCount}, max {$maxCount})");
        }
    }

    echo "Thème (query) [sans thème] : ";
    $queryInput = function_exists('readline') ? readline() : fgets(STDIN);
    $queryInput = (string) $queryInput;
    if (strpos($queryInput, chr(27)) !== false) {
        $queryInput = '';
    }
    $query = trim($queryInput);
    if ($query === '') {
        $query = '';
    }
    
    echo "\n\n...Téléchargement de l'image d'origine (random)...\n";
    echo "...Téléchargement de {$count} image(s) de test random" . ($query ? " (thème: {$query})" : "") . "...\n";
    $setupResult = $setupService->run($count, $query, true);
    $downloadedCount = (int) $setupResult['downloadedTests'];
    echo "[OK] Image d'origine sauvegardée\n\n";
    echo "[OK] {$downloadedCount} image(s) de test téléchargée(s)\n";
    
    echo "\n[INFO] Setup terminé!\n";
    echo " {$downloadedCount} images téléchargées\n";
    echo " Temps d'exécution: " . number_format(microtime(true) - $setupStart, 2) . "s\n";
    $ramEnd = memory_get_usage(false);
    $peakEnd = memory_get_peak_usage(true);
    echo sprintf(
        " RAM (used): %s -> %s (Δ=%s)\n",
        BytesFormatter::format($ramStart),
        BytesFormatter::format($ramEnd),
        BytesFormatter::format(max(0, $ramEnd - $ramStart))
    );
    echo sprintf(
        " Pic RAM (alloc): %s -> %s (Δ=%s)\n",
        BytesFormatter::format($peakStart),
        BytesFormatter::format($peakEnd),
        BytesFormatter::format(max(0, $peakEnd - $peakStart))
    );
    echo " Vous pouvez maintenant lancer: php index.php\n\n";
    
} catch (Exception $e) {
    echo "\n[FAIL] Erreur: {$e->getMessage()}\n";
    echo " Dans: {$e->getFile()}:{$e->getLine()}\n\n";
    echo " Temps d'exécution: " . number_format(microtime(true) - $setupStart, 2) . "s\n\n";
    $ramEnd = memory_get_usage(false);
    $peakEnd = memory_get_peak_usage(true);
    echo sprintf(
        " RAM (used): %s -> %s (Δ=%s)\n",
        BytesFormatter::format($ramStart),
        BytesFormatter::format($ramEnd),
        BytesFormatter::format(max(0, $ramEnd - $ramStart))
    );
    echo sprintf(
        " Pic RAM (alloc): %s -> %s (Δ=%s)\n\n",
        BytesFormatter::format($peakStart),
        BytesFormatter::format($peakEnd),
        BytesFormatter::format(max(0, $peakEnd - $peakStart))
    );
    exit(1);
}

