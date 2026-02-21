<?php
/**
 * Script de setup - Téléchargement des images depuis Unsplash
 * Utilisation du pattern Factory/Builder
 */

require_once __DIR__ . '/loader.php';

echo "\n";
echo "╔══════════════════════════════════════════════════════╗\n";
echo "║        SETUP - Téléchargement des images             ║\n";
echo "╚══════════════════════════════════════════════════════╝\n\n";
echo "\n";

$setupStart = microtime(true);

try {
    // Récupération de la configuration via Singleton
    $config = CConfigurationManager::getInstance();
    
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
    $minCount = 3;
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
    
    // Téléchargement de l'image d'origine (random)
    echo "\n\n...Téléchargement de l'image d'origine (random)...\n";
    $originPaths = $apiClient->downloadRandomImagesWithProgress(1, $query, "Chargement origine", true);
    if (empty($originPaths)) {
        throw new RuntimeException("Impossible de télécharger l'image d'origine");
    }
    rename($originPaths[0], $originPath);
    echo "[OK] Image d'origine sauvegardée\n\n";
    
    // Téléchargement des images de test (random)
    echo "...Téléchargement de {$count} image(s) de test random" . ($query ? " (thème: {$query})" : "") . "...\n";
    $downloadedCount = 0;
    try {
        $paths = $apiClient->downloadRandomImagesWithProgress($count, $query, "Chargement images test", true);
        $downloadedCount = is_array($paths) ? count($paths) : 0;
        echo "[OK] {$downloadedCount} image(s) de test téléchargée(s)\n";
    } catch (Exception $e) {
        echo "    ⚠️  Erreur: {$e->getMessage()}\n";
    }
    
    echo "\n[INFO] Setup terminé!\n";
    echo " {$downloadedCount} images téléchargées\n";
    echo " Temps d'exécution: " . number_format(microtime(true) - $setupStart, 2) . "s\n";
    echo " Vous pouvez maintenant lancer: php index.php\n\n";
    
} catch (Exception $e) {
    echo "\n[FAIL] Erreur: {$e->getMessage()}\n";
    echo " Dans: {$e->getFile()}:{$e->getLine()}\n\n";
    echo " Temps d'exécution: " . number_format(microtime(true) - $setupStart, 2) . "s\n\n";
    exit(1);
}

