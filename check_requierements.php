<?php

// Script de vérification des prérequis pour le projet*

// TODO : Ajouter des couleurs pour les messages d'erreur et de succès

// Initialisation d'un tableau pour stocker les erreurs rencontrées lors des vérifications
$errors = [];

echo "\n";
echo "╔═══════════════════════════════════════════════╗\n";
echo "║   Vérification des prérequis pour le projet   ║\n";
echo "╚═══════════════════════════════════════════════╝\n";
echo "\n";

/////// Vérifications de : ///////

// 1. la version de PHP > requise
echo "\n";
$requiredPhpVersion = '7.4';
$currentPhpVersion = phpversion();
if (version_compare($currentPhpVersion, $requiredPhpVersion, '>=')) {
    echo "[OK] Version de PHP : $currentPhpVersion\n";
} else {
    echo "[FAIL] Version de PHP insuffisante : $currentPhpVersion\n";
    echo "       ║ Marche à suivre : \n";
    echo "       ║ Mettez à jour votre version de PHP vers la version $requiredPhpVersion ou supérieure.\n";
    $errors[] = "Version de PHP insuffisante : $currentPhpVersion"; };

// 2. Présence de l'extension GD, permettant de manipuler les images
echo "\n";
if (extension_loaded('gd')) {
    echo "[OK] Présence de l'extension GD\n";
} else {
    echo "[FAIL] Extension GD manquante\n";
    echo "       ║ Marche à suivre : \n";
    echo "       ║ Ouvrez le fichier php.ini et décommentez la ligne suivante :\n";
    echo "       ║ extension=gd\n";
    echo "       ║ Ensuite, redémarrez votre serveur web pour appliquer les changements.\n";
    $errors[] = 'Extension GD manquante';};


// 3. Présence de l'extension CURL, utilisée pour faire des requêtes HTTP \n
echo "\n";
if (extension_loaded('curl')) {
    echo "[OK] Présence de l'extension CURL\n";
} else {
    echo "[FAIL] Extension CURL manquante\n";
    echo "       ║ Marche à suivre : \n";
    echo "       ║ Ouvrez le fichier php.ini et décommentez la ligne suivante :\n";
    echo "       ║ extension=curl\n";
    echo "       ║ Ensuite, redémarrez votre serveur web pour appliquer les changements.\n";
    $errors[] = 'Extension CURL manquante'; };

// 4. Présence du fichier .env et des variables d'environnement nécessaires au projet
echo "\n";
$UnsplashInfo = [ 'UNSPLASH_APPLICATION_ID', 'UNSPLASH_ACCESS_KEY', 'UNSPLASH_SECRET_KEY', 'UNSPLASH_SSL_VERIFY' ];
if (file_exists('.env')) {
    echo "[OK] Présence du fichier .env \n";
    echo "\n";
    
    // Liste des variables d'environnement à vérifier pour l'API Unsplash
    $envContent = file_get_contents('.env');
    $missing = [];

    if ($envContent === false) {
        $missing = $UnsplashInfo;
    } else {
        foreach ($UnsplashInfo as $key) {
            $value = null;
            // Utilisation d'une expression régulière pour extraire la valeur de la variable d'environnement
            // Cette regex permet de capturer les lignes du type :
            //   - KEY=value
            //   - export KEY=value
            // Elle ignore les espaces autour du signe égal et les guillemets autour de la valeur
            // Note : Cette regex ne gère pas les cas où la valeur contient des sauts de ligne ou des guillemets imbriqués
            if (preg_match('/^\s*(?:export\s+)?' . preg_quote($key, '/') . '\s*=\s*(.*?)\s*$/m', $envContent, $m)) {
                $value = trim($m[1], " \t\n\r\0\x0B\"'");
            }
            if ($value === null || $value === '') {
                $missing[] = $key;
            }
        }
    }

    if (!empty($missing)) {
        echo "[FAIL] Variables d'environnement Unsplash manquantes ou vides\n";
        echo "       ║ Marche à suivre : \n";
        echo "       ║ Ouvrez le fichier .env\n" ;
        echo "       ║ Assurez-vous que les variables suivantes sont correctement définies :\n";
        echo "       ║ - " . implode("\n       ║ - ", $missing) . "\n";
        $errors[] = 'Variables d\'environnement Unsplash manquantes ou vides';
    }
} else {
    echo "[FAIL] Fichier .env manquant\n";
    echo "       ║ Marche à suivre : \n";
    echo "       ║ Créez un fichier nommé .env à la racine du projet\n";
    echo "       ║ et ajoutez les variables d'environnement nécessaires:\n";
    echo "       ║ " . implode("\n       ║ ", $UnsplashInfo) . "\n";
    $errors[] = 'Fichier .env manquant'; };

if (empty($errors)) {
    echo "\n";
    echo "╔═══════════════════════════════════════════════╗\n";
    echo "║   Tous les prérequis sont satisfaits !        ║\n";
    echo "║   Vous pouvez maintenant lancer le projet.    ║\n";
    echo "╚═══════════════════════════════════════════════╝\n";
} else {
    echo "\n";
    echo "╔═══════════════════════════════════════════════════════════════════════╗\n";
    echo "║                                                                       ║\n";
    echo "║   Des erreurs ont été rencontrées :                                   ║\n";
    foreach ($errors as $error) {
        echo "║          - $error\n";
    }
    echo "║                                                                       ║\n";
    echo "║   Veuillez corriger ces erreurs avant de lancer le projet.            ║\n";
    echo "║                                                                       ║\n";
    echo "╚═══════════════════════════════════════════════════════════════════════╝\n";
}
