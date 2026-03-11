<?php
/**
 * ════════════════════════════════════════════════════════════════════════
 * INDEX.PHP — Mémoire & Référence : gestion de la RAM avec PHP
 * ════════════════════════════════════════════════════════════════════════
 *
 * Ce script a une DOUBLE vocation :
 *
 *   1. RÉFÉRENCE  — Mémento sur la gestion mémoire en PHP :
 *      définitions (RAM, heap, stack, GC, swap, memory thrashing, fuites…),
 *      fonctions PHP utiles, et comparaisons avec d'autres langages.
 *
 *   2. DÉMONSTRATION — Le flux d'analyse d'images de RGBMatch sert
 *      d'illustration concrète : chaque choix architectural est expliqué
 *      par ses effets mesurés sur la mémoire.
 *
 * Usage :  php index.php
 *
 * @author  Margot Hourdillé
 * @project RGBMatch — Challenge Firstruner
 * @version 2.0.0
 * @date    2026-02-20
 * @update  2026-03-06
 */

require_once __DIR__ . '/loader.php';

use RGBMatch\Api\GdImageLoader;
use RGBMatch\Api\RgbImageAnalyzer;
use RGBMatch\Application\ConsoleRenderer;
use RGBMatch\Application\ServiceFactory;
use RGBMatch\Metier\BytesFormatter;
use RGBMatch\Metier\RamDeltaPresenter;
use RGBMatch\Singletons\ConfigurationManager;

// ─── Rendu texte si lancé depuis un navigateur ──────────────────────
if (PHP_SAPI !== 'cli' && PHP_SAPI !== 'phpdbg') {
    header('Content-Type: text/plain; charset=utf-8');
}

// ═══════════════════════════════════════════════════════════════════
//  EXÉCUTION PRINCIPALE
// ═══════════════════════════════════════════════════════════════════

// Filet de sécurité : si une erreur survient n'importe où dans le script,
// on l'attrape ici pour afficher un message lisible au lieu du rapport
// technique brut que PHP cracherait par défaut.

try {
    // Initialisation des outils de rendu et de mesure
    $ui  = new ConsoleRenderer();
    $ram = new RamDeltaPresenter();
    // Nettoyage préliminaire : collecter les cycles orphelins et vider les caches
    gc_collect_cycles();
    // PHP 8.1+ : purge les caches internes du GC (utile avant de mesurer les deltas RAM)
    if (function_exists('gc_mem_caches')) {
        gc_mem_caches();
    }
    // Snapshot de départ pour mesurer les deltas RAM tout au long du script
    $ramStart = $ram->snapshot();
    $ram->setBaseFromSnapshot($ramStart);

    // ╔══════════════════════════════════════════════════════════════╗
    // ║  INTRODUCTION                                                ║
    // ╚══════════════════════════════════════════════════════════════╝
    
    $ui->banner();

    echo <<<'INTRO'
  Ce script sert à la fois :
    • de MÉMENTO sur les termes et concepts de gestion mémoire en PHP,
    • de DÉMONSTRATION par l'exemple via le flux RGBMatch (tri Top 3).

  Chaque notion théorique sera illustrée par des mesures réelles
  issues de l'analyse d'images qui suit.

INTRO;

    $ml = BytesFormatter::parseMemoryLimit();
    echo sprintf("\nDonnées de départ :\n");
    echo sprintf("  PHP %s  |  memory_limit = %s\n",
        PHP_VERSION,
        $ml === null ? 'illimité (-1)' : BytesFormatter::format($ml)
    );
    echo sprintf("  RAM de départ : used = %s  |  alloc = %s\n",
        BytesFormatter::format($ramStart['used']),
        BytesFormatter::format($ramStart['alloc'])
    );

    $ui->pressEnter();

    // ╔══════════════════════════════════════════════════════════════╗
    // ║  CHAPITRE 1 — Qu'est-ce que la RAM ?                         ║
    // ╚══════════════════════════════════════════════════════════════╝
    $ui->section('Chapitre 1 — Qu\'est-ce que la RAM ?');
    $ui->printTextBox([
        'Pourquoi « Random » ?',
        '  Un disque dur HDD (Hard Disk Drive) lit les données de façon',
        '  séquentielle : pour atteindre l\'octet n°1000, il faut parcourir',
        '  les 999 précédents. La RAM, elle, tout comme le SSD (Solid ',
        '  State Drives), accède à n\'importe quelle adresse en temps ',
        '  constant d\'où "Random Access".',
        '',
        'D\'ou vient le terme de « volatile » que l\'on utilise pour la RAM ?',
        '  Il s\'agit juste d\'un terme pour signifier que la mémoire est ',
        '  temporaire. Contrairement à un SSD ou un HDD, la RAM ',
        '  perd son contenu dès que le courant est coupé.',
    ],  [
        'indent' => '  ',
        'title' => 'RAM = Random Access Memory',
        'titleAlign' => 'center',
    ]);
    $ui->pressEnter();

    // ╔══════════════════════════════════════════════════════════════╗
    // ║  CHAPITRE 2 — Stack & Heap                                   ║
    // ╚══════════════════════════════════════════════════════════════╝
    $ui->section('Chapitre 2 — Stack (pile) & Heap (tas)');
    echo "\nLa RAM est segmentée en plusieurs zones principales, dont les deux suivantes :\n\n";

    $ui->printTextBox([
      '• Structure LIFO (Last In, First Out)',
      '- Dernier élément ajouté, premier retiré.',
      '- Exemple : une pile d\'assiettes, où l\'on retire celle du dessus en premier.',
      '• Stocke : variables locales, paramètres de fonctions, adresses de retour (utilisées pour revenir à l\'instruction suivante après un appel de fonction).',
      '• Allocation/libération automatique et ultra-rapide.',
      '• Taille fixe (souvent 1–8 Mo par thread).',
      '• Pas de fragmentation, car les blocs sont libérés dans l\'ordre inverse de leur allocation.',
    ], [
      'indent' => '  ',
      'width' => 62,
      'title' => 'STACK (pile)',
    ]);

    echo "\n";

    $ui->printTextBox([
      '• Zone mémoire DYNAMIQUE*, de taille variable.',
      '• Stocke : objets, tableaux, grosses structures, ressources (comme les images GD en PHP).',
      '• Allocation plus lente, car le système doit rechercher des blocs libres adaptés.',
      '• Sujet à la FRAGMENTATION, car les blocs ne sont pas libérés dans un ordre prévisible.',
      '• Exception : les closures (ou "fonctions anonymes") capturent les variables locales nécessaires et les stockent dans le heap pour garantir leur persistance, même après la fin de la portée de la fonction où elles ont été définies.',
    ], [
      'indent' => '  ',
      'width' => 62,
      'title' => 'HEAP (tas)',
    ]);
    echo <<<'NOTE'

    * Le terme "dynamique" dans le contexte du heap signifie que
    la mémoire est allouée et libérée à la demande, pendant l'exécution
    du programme, contrairement au stack, où la mémoire est allouée
    de manière fixe et automatique.
    NOTE;
    echo "\n\n";
    $ui->printTextBox([
        '• Les **variables locales** :',
        '  sont stockées dans le stack et sont accessibles uniquement ',
        '  dans le contexte de la fonction où elles sont définies.',
        '• Les **variables globales** :',
        '  sont stockées dans une zone spéciale du heap et restent accessibles',
        '  tout au long de l\'exécution du script, quel que soit le contexte.',
        '• Les closures :',
        '  permettent de capturer des variables locales et de les rendre ',
        '  persistantes en les déplaçant dans le heap. Elles correspondent ',
        '  donc à une sorte d\'hybride : elles sont définies dans une fonction',
        '  (stack) mais leurs données sont stockées dans le heap pour garantir',
        '  leur durée de vie au-delà de la portée de la fonction et la sécurité ',
        '  d\'accès (contexte local).',
        '',
        ], [
          'indent' => '  ',
          'width' => 62,
          'title' => 'Variables locales vs globales :',
        ]);
        $ui->printTextBox([
        '  Presque tout passe par le heap, géré par le Zend Memory Manager',
        '  (composant clé du moteur Zend, qui est le cœur de PHP).',
        '  Le ZMM est responsable de l\'allocation, il contient un ',
        '  ramasse-miettes (GC) qui détecte et libère la mémoire inutilisée.',
        '  ZMM utilise un système de comptage de références pour savoir ',
        '  combien de variables pointent vers une même valeur.',
        '  Lorsque le compteur atteint zéro, la mémoire est libérée immédiatement.',
        '  En cas de références circulaires (par exemple, deux objets qui se',
        '  référencent mutuellement), le Garbage Collector intervient pour ',
        '  détecter et nettoyer ces cycles.',
        ], [
          'indent' => '  ',
          'width' => 62,
          'title' => 'En PHP :',]);
    $ui->pressEnter();

    // ╔══════════════════════════════════════════════════════════════╗
    // ║  CHAPITRE 3 — Le Garbage Collector (ramasse-miettes)         ║
    // ╚══════════════════════════════════════════════════════════════╝
    $ui->section('Chapitre 3 — Le Garbage Collector (GC)');

    echo "\nPHP utilise DEUX mécanismes complémentaires :\n\n";
    $ui->printTextBox([
        '',
        '   Chaque variable (zval) possède un compteur : combien de "noms"',
        '   pointent vers cette valeur ?',
        '',
        '     $a = new GdImage();   // refcount = 1',
        '     $b = $a;              // refcount = 2  (même objet)',
        '     unset($a);            // refcount = 1',
        '     unset($b);            // refcount = 0 → mémoire LIBÉRÉE IMMÉDIATEMENT',
        '',
        '   C\'est pourquoi unset() ou $var = null provoque une libération',
        '   instantanée si le refcount tombe à 0.',
        '',
        ], [
          'indent' => '  ',
          'width' => 62,
          'title' => '1. REFERENCE COUNTING (comptage de références)',]);
    $ui->printTextBox([
        '',
        '   Le refcount seul ne détecte PAS les références circulaires :',
        '',
        '     $a = new stdClass();',
        '     $b = new stdClass();',
        '     $a->ref = $b;',
        '     $b->ref = $a;',
        '     unset($a, $b);',
        '     // refcount ne tombe jamais à 0 → FUITE sans le cycle collector',
        '     // Avec gc_collect_cycles(), ces objets sont détectés comme orphelins',
        '     // cad inaccessibles ( plus de référence mémoire directe )',
        '     // → ils sont détruits → mémoire libérée.',
        '',
        '   Le cycle collector parcourt le "root buffer" (10 000 entrées',
        '   par défaut) et identifie les cycles orphelins pour les détruire.',
        '   Il se lance automatiquement ou manuellement avec gc_collect_cycles().',
        '',
        ], [
          'indent' => '  ',
          'width' => 62,
          'title' => '2. CYCLE COLLECTOR (gc_collect_cycles)',]);
    echo <<<'NOTE2'
        ⚠  Point clé :
          Si le refcount tombe à 0, le GC n'a rien à faire.
          Le GC n'est utile QUE pour les cycles.
          → C'est pourquoi dans les tableaux ci-dessous, "unset" libère
          souvent la mémoire, mais "GC" ne fait rien : il n'y a pas
          de cycle à collecter. Il est souvent malgré tout bon de l'appeler
          après un unset, au cas où il y aurait des cycles à nettoyer.
          Sorte de filet de sécurité.
        
        Nota : Python est similaire à PHP avec un "refcount" + "cycle detector"
    NOTE2;
    $ui->pressEnter();

    // ╔══════════════════════════════════════════════════════════════╗
    // ║  CHAPITRE 4 — Fuites mémoire, swap, memory thrashing         ║
    // ╚══════════════════════════════════════════════════════════════╝
    $ui->section('Chapitre 4 — Fuites mémoire, swap & thrashing  ');
    $ui->printTextBox([
        'Définition : mémoire allouée qui n\'est jamais libérée et qui',
        's\'accumule au fil de l\'exécution.',
        '',
        'En PHP :',
        '  • références circulaires non collectées,',
        '  • variables globales / statiques qui grossissent à chaque requête,',
        '  • caches internes jamais purgés,',
        '  • event listeners / callbacks jamais retirés.',
        '',
        'Conséquences :',
        '  → used grimpe à chaque itération',
        '  → Fatal error: Allowed memory size of X bytes exhausted',
        '  → En production : le process ralentit, puis crash ',
        '  (OOM kill : Out Of Memory kill)',
        '  L\'OOM Killer identifie les processus qui consomment le plus ',
        '  de mémoire et les termine brutalement pour libérer des ressources.',
        '',
        ], [
          'indent' => '  ',
          'width' => 62,
          'title' => 'FUITES MEMOIRE (Memory Leak)',]);
        $ui->printTextBox([
        'Quand la RAM physique (matérielle) est pleine, l\'OS déplace des ',
        'pages mémoire vers le disque dur ou SSD (swap file / swap partition). ',
        'Le programme ne le sait pas : pour lui, la mémoire semble toujours disponible.',
        'Mais l\'accès disque est ~1000x plus lent que la RAM…',
        '',
        ], [
          'indent' => '  ',
          'width' => 62,
          'title' => 'SWAP (fichier d\'échange)',]);
        $ui->printTextBox([
        'Si le système passe son temps à transférer des pages entre RAM',
        'et swap (page-in ↔ page-out), les performances S\'EFFONDRENT.',
        'Le CPU attend le disque au lieu de calculer → c\'est le « thrashing ».',
        '',
        'C\'est exactement la raison pour laquelle on évite de charger 30 images GD',
        'en RAM simultanément. Même si PHP ne crashe pas (memory_limit),',
        'l\'OS pourrait swapper → thrashing → temps d\'analyse x100.',
        ], [
          'indent' => '  ',
          'width' => 62,
          'title' => 'MEMORY TRASHING',]);
    $ui->pressEnter();

    // ╔══════════════════════════════════════════════════════════════╗
    // ║  CHAPITRE 5 — Fonctions PHP pour la gestion mémoire          ║
    // ╚══════════════════════════════════════════════════════════════╝
    $ui->section('Chapitre 5 — Fonctions PHP utiles');
    $ui->printTable([
      'Fonction',
      'Rôle',
    ], [
      ['memory_get_usage(false)', 'Mémoire UTILISÉE (used) : nos variables'],
      ['memory_get_usage(true)', 'Mémoire ALLOUÉE (alloc) : réservée par PHP'],
      ['memory_get_peak_usage(true)', 'PIC d\'allocation (record, ne redescend pas)'],
      ['unset($var)', 'Supprime la référence → refcount - 1'],
      ['$var = null', 'Remplace la valeur → libère l\'ancienne'],
      ['gc_collect_cycles()', 'Force le cycle collector (références circulaires)'],
      ['gc_mem_caches() (PHP 8.1+)', 'Purge les caches internes du GC'],
      ['gc_status()', 'Statistiques : runs, collected, threshold…'],
      ['ini_get(\'memory_limit\')', 'Limite mémoire du script courant'],
      ['ini_set(\'memory_limit\', \'256M\')', 'Modifier la limite (si autorisé)'],
    ], [
      'indent' => '  ',
      'minWidths' => [33, 55],
      'maxWidths' => [33, 55],
      'columnAlign' => ['left', 'left'],
    ]);

    $ui->printTextBox([
        'used   = memory_get_usage(false)',
        '         → Combien le script occupent réellement.',
        '',
        'alloc  = memory_get_usage(true)',
        '         → Combien PHP a RÉSERVÉ auprès de l\'OS.',
        '         Toujours >= used. L\'excédent est une réserve (pool).',
        '',
        'pic    = memory_get_peak_usage(true)',
        '         → Record historique d\'allocation. Ne redescend JAMAIS.',
    ], [
      'indent' => '  ',
      'title' => 'Données de mesure utilisées dans ce script',
      ]);
    $ui->pressEnter();

    // ╔══════════════════════════════════════════════════════════════╗
    // ║  CHAPITRE 6 — Pourquoi les images explosent la RAM           ║
    // ╚══════════════════════════════════════════════════════════════╝
    $ui->section('Chapitre 6 — Images & RAM : GD (Graphics Draw)');
    $ui->printTextBox([
        'Sur disque, une image JPEG est compressée :',
        '  Fichier disque : ~50 Ko à ~500 Ko',
        '',
        'En RAM, la librairie GD la décompresse en "BITMAP brut" :',
        '  Chaque pixel = 4 octets (R, G, B, Alpha)',
        '  RAM = largeur x hauteur x 4 octets',
    ], ['indent' => '  ',
        'title' => 'Taille image en RAM',
    ]);

    // Illustration avec l'image d'origine réelle
    $config     = ConfigurationManager::getInstance();
    $originPath = (string) $config->get('paths.origin');
    $testDir    = (string) $config->get('paths.test');

    if (!file_exists($originPath)) {
        throw new RuntimeException("Image d'origine introuvable. Lancez setup.php d'abord.");
    }

    $meta = GdImageLoader::getImageMeta($originPath);
    $diskSize = is_file($originPath) ? (int) filesize($originPath) : 0;
    $estimated = GdImageLoader::estimateRamBytes($meta['w'], $meta['h']);

    echo sprintf("  \nExemple concret — notre image d'origine :\n");
    echo sprintf("    Fichier : %s\n", basename($originPath));
    echo sprintf("    Disque  : %s (compressé JPEG)\n", BytesFormatter::format($diskSize));
    if ($meta['w'] > 0) {
        echo sprintf("    Pixels  : %d x %d = %s pixels\n", $meta['w'], $meta['h'],
            number_format($meta['w'] * $meta['h'], 0, ',', ' '));
        echo sprintf("    RAM GD  : %d x %d x 4 = %s (bitmap brut)\n\n",
            $meta['w'], $meta['h'], BytesFormatter::format($estimated));
        $factor = $diskSize > 0 ? round($estimated / $diskSize) : 0;
        if ($factor > 1) {
            echo sprintf("    → Facteur d'expansion : x%d — l'image pèse %d fois plus en RAM ", $factor, $factor);
            echo "que sur disque.\n";
        }
    }

    $ui->printTextBox([
        'C\'est la raison pour laquelle il est important de :',
        '  1. Libérer l\'image GD dès que les données utiles sont extraites',
        '  2. Ne pas garder toutes les images en RAM simultanément',
        '  3. Traiter en streaming (une par une)',
        '',
        '→ C\'est de cette manière que cela est prévu dans RGBMatch.',
    ], ['indent' => '  ']);
    $ui->pressEnter();

    // ╔══════════════════════════════════════════════════════════════╗
    // ║  CHAPITRE 7 — l'approche : streaming Top 3                   ║
    // ╚══════════════════════════════════════════════════════════════╝
    $ui->section('Chapitre 7 — Architecture : streaming Top 3');
    $ui->printTextBox([
        'X  Approche primitive : tout charger, tout comparer, puis trier',
        '    → Avec 30 images de ~8 Mo chacune = ~240 Mo en RAM',
        '    → Risque de dépasser memory_limit (par défaut de 128 Mo)',
        '    → Risque de thrashing si la RAM physique est saturée',
        '',
        '✓  Approche privilégie ici : streaming itératif',
        '    → On traite UNE image à la fois',
        '    → Pic RAM largement réduite = 1 seule image GD à tout instant',
        '    → On ne conserve que le Top 3 en mémoire (objets légers)',
        '    → Chaque image est libérée avant de charger la suivante',
        '    → RAM maîtrisée, pas de risque de dépassement ni de thrashing',
    ], ['indent' => '  ',
        'width' => 62,
        'title' => 'Comparaison des approches',
    ]);

    $ui->printTextBox([
        '1. Pré-check     Vérifie existence + détecte le type',
        '2. Load GD       Décompresse → gros pic RAM (heap)',
        '3. Downscale     Réduit la taille',
        '4. Analyse RGB   Lit les pixels avec échantillonnage (CPU)',
        '5. Free GD       Libère l\'image GD → used retombe',
        '6. Build objets  Crée les Value Objects légers',
        '7. Ranking       Garde seulement les 3 meilleurs',
        '8. unset         Retire les références (refcount → 0)',
        '9. GC            Collecte les éventuels cycles orphelins',
    ], [
        'indent' => '  ',
        'width' => 57,
        'title' => 'Pipeline par image',
    ]);

    $ui->printTextBox([
        'Ce qui est observable dans les tableaux qui vont suivre :',
        '  • « Load GD » montre l\'explosion de used (+5 à +12 Mo)',
        '  • « Free GD » montre la libération symétrique',
        '  • « unset » : petite libération si l\'image n\'est pas dans le Top 3',
        '    (refcount passe à 0 → objet détruit)',
        '  • « GC » montre souvent +0 B : pas de cycles → rien à faire',
        '  • Le résidu (cumulé final ~3 Ko) = structures PHP internes',
    ], ['indent' => '  ',
        'width' => 62,
        'title' => 'Observations RAM',
    ]);
    $ui->pressEnter();

    // ╔══════════════════════════════════════════════════════════════╗
    // ║  CHAPITRE 8 — Préparation : configuration & dépendances      ║
    // ╚══════════════════════════════════════════════════════════════╝
    $ui->section('Chapitre 8 — Préparation   ');

    echo "\n";
    $ui->withDots('  Chargement de la configuration', 4, 250);

    $t0 = microtime(true);
    $testImagePaths = glob($testDir . '/*.jpg');
    if (!is_array($testImagePaths) || count($testImagePaths) < 6) {
        throw new RuntimeException("Pas assez d'images de test (min 6). Lancez setup.php d'abord.");
    }
    $testsCount = count($testImagePaths);
    $ui->traceTime('Configuration chargée', microtime(true) - $t0);
    echo sprintf("  → %d images de test trouvées dans %s\n", $testsCount, basename($testDir));
    echo sprintf("  RAM : %s\n\n", $ram->formatLine($ram->snapshot()));

    $ui->withDots('  Construction des dépendances (Factory + DI)', 3, 250);

    $sampleRate      = 10;     // 1 pixel sur 10 — aligné avec public/index.php pour cohérence des mesures
    $maxDimension    = 0;       // pas de downscale

    $t0 = microtime(true);
    $imageLoader = ServiceFactory::createImageLoader();
    $analyzer    = new RgbImageAnalyzer($imageLoader, [
        'sampleRate'      => $sampleRate,
        'maxDimension'    => $maxDimension,
    ]);
    [$imageDataBuilder, $comparisonResultBuilder, $ranker] = ServiceFactory::createImageServicesFromAnalyzer($analyzer);
    $ui->traceTime('Dépendances construites', microtime(true) - $t0);

    echo sprintf("  Paramètres : sampleRate=%d | maxDimension=%d\n",
      $sampleRate, $maxDimension);
    echo sprintf("  RAM : %s\n", $ram->formatLine($ram->snapshot()));

    echo "\n";
    $ui->withDots('  Analyse de l\'image d\'origine', 4, 300);

    $t0 = microtime(true);
    $originImage = $imageDataBuilder->build($originPath);
    $ui->traceTime('Analyse RGB (origin)', microtime(true) - $t0);
    echo sprintf("  RAM après origin : %s\n", $ram->formatLine($ram->snapshot()));
    echo "  → L'image GD a été libérée ; on ne garde qu'un objet léger (chemin + RGB %).\n";

    $ui->pressEnter();

    // ╔══════════════════════════════════════════════════════════════╗
    // ║  CHAPITRE 9 — Comparaison de l'ordre de nettoyage            ║
    // ╚══════════════════════════════════════════════════════════════╝
    $ui->section('Chapitre 9 — Comparaison de l\'ordre de nettoyage');

    $ui->printTextBox([
        'Nous allons analyser toutes les images de test, mais nous allons afficher',
        'ici que les analyses des 6 premières, car elles sont suffisantes pour observer',
        'les deltas RAM et comprendre l\'impact de l\'ordre des étapes de nettoyage.',
        '',
        'Nous analysons 6 images, chacune avec un ORDRE DIFFÉRENT',
        'des 3 étapes de nettoyage : Free GD, unset, GC.',
        'Les 6 permutations possibles sont testées :',
        '',
        '  #1  Free GD     → unset     → GC',
        '  #2  Free GD     → GC        → unset',
        '  #3  unset       → GC        → Free GD',
        '  #4  unset       → Free GD   → GC',
        '  #5  GC          → Free GD   → unset',
        '  #6  GC          → unset     → Free GD',
        '',
        'Objectif : observer si l\'ORDRE change les deltas RAM',
        'et comprendre ce que chaque étape libère RÉELLEMENT.',
    ], ['indent' => '  ']);

    $permSummary = [];
    $provider = ServiceFactory::createMeasurementPayloadProvider(__DIR__);
    $payload = $provider->getPayload([
      'sampleRate' => $sampleRate,
      'maxDimension' => $maxDimension,
      'permCount' => 6,
    ]);

    foreach ($payload['images'] as $report) {
      if (!isset($report['perm'])) {
        continue;
      }

      $idx           = $report['index'];
      $filename      = $report['filename'];
      $fileSizeBytes = $report['fileSizeBytes'];
      $steps         = $report['steps'];
      $similarity    = $report['similarity'];
      $perm          = $report['perm'];
      $top3Simple    = $report['top3'];
      $cleanupDeltas = $report['cleanupDeltas'];

      $permSummary[] = [
        'idx'      => $idx,
        'label'    => $perm['label'],
        'filename' => $filename,
        'deltas'   => $cleanupDeltas,
      ];

      echo "\n" . str_repeat('─', 80) . "\n";
      $ui->withDots(sprintf('Analyse de l\'image %d/6 ', $idx), 4, 300);
      echo sprintf("  Image %d/6 : %s (%s sur disque)\n", $idx, $filename, BytesFormatter::format($fileSizeBytes));
      echo sprintf("  Ordre de nettoyage : %s\n", $perm['label']);
      echo str_repeat('─', 80) . "\n";

      $ui->printRamTable($steps);

      echo sprintf("\n  Similarité : %.2f%%", $similarity);

      if (!empty($top3Simple)) {
        echo "\n  Top 3 actuel :";
        foreach ($top3Simple as $rank => $t) {
          echo sprintf("\n      #%d  %s (%.1f%%)", $rank + 1, $t['filename'], $t['similarity']);
        }
      }

      $totalUsed = 0;
      foreach ($steps as $st) {
        $totalUsed += (int) ($st['deltaUsed'] ?? 0);
      }
      $sign = $totalUsed < 0 ? '' : '+';
      echo sprintf("\n  Bilan RAM : %s%s", $sign, BytesFormatter::format(abs($totalUsed)));
      if ($totalUsed <= 0) {
        echo " ✓ pas de fuite";
      } else {
        echo " (résidu — objets PHP internes)";
      }
      echo "\n";
    }

    // ══════════════════════════════════════════════════════════════
    //  CONCLUSION DU CHAPITRE 9 — Comparaison des ordres
    // ══════════════════════════════════════════════════════════════
    echo "\n" . str_repeat('═', 80) . "\n";
    echo "  CONCLUSION — Comparaison des 6 ordres de nettoyage\n";
    echo str_repeat('═', 80) . "\n\n";

    $fmtS = static function (int $b): string {
      return ($b < 0 ? '-' : '+') . BytesFormatter::format(abs($b));
    };

    $headers = ['#', 'Ordre', 'ΔFree GD', 'Δunset', 'ΔGC'];
    $rows = [];
    foreach ($permSummary as $ps) {
      $rows[] = [
        '#' . (string) ($ps['idx'] ?? ''),
        (string) ($ps['label'] ?? ''),
        $fmtS((int) ($ps['deltas']['freeGd'] ?? 0)),
        $fmtS((int) ($ps['deltas']['unset'] ?? 0)),
        $fmtS((int) ($ps['deltas']['gc'] ?? 0)),
      ];
    }


    $ui->printTable($headers, $rows, [
      'indent' => '  ',
      'minWidths' => [3, 32, 11, 11, 11],
      'maxWidths' => [3, 32, 11, 11, 11],
      'columnAlign' => ['center', 'left', 'center', 'center', 'center'],
    ]);

    $ui->printTextBox([
        'Ce qu\'on peut DÉDUIRE de ces 6 permutations :',
        '──────────────────────────────────────────────',
        '1. FREE GD est TOUJOURS l\'étape qui libère le plus de mémoire.',
        '   C\'est elle qui appelle imagedestroy() et libère le bitmap brut (plusieurs Mo).',
        '   Peu importe qu\'elle soit en 1er, 2e ou 3e.',
        '',
        '2. UNSET libère les objets légers (CImageData, CComparisonResult, etc.)',
        '   SEULEMENT si leur refcount tombe à 0.',
        '   → Si l\'objet est dans le Top 3, unset ne libère RIEN.',
        '   → Le delta est de quelques centaines d\'octets au mieux.',
        '',
        '3. GC (gc_collect_cycles) ne trouve RIEN à faire : pas de référence circulaire.',
        '   Son delta est toujours 0. Il sert uniquement de filet de sécurité.',
        '',
        '4. L\'ORDRE NE CHANGE PAS LE RÉSULTAT FINAL : le bilan RAM est identique.',
        '   PHP libère EXACTEMENT la même quantité de mémoire au total.',
        '',
        '5. L\'ordre OPTIMAL est : Free GD → unset → GC',
        '   → On libère le GROS en premier (bitmap), puis les petits (objets),',
        '     puis le filet de sécurité (GC).',
        '   → Cela minimise le TEMPS où la RAM est haute (pic plus court).',
        '',
        '6. L\'ordre le MOINS efficace est : GC → unset → Free GD',
        '   → Le bitmap GD reste en RAM plus longtemps.',
        '',
        'RÉSUMÉ : L\'ordre affecte la DURÉE du pic, pas le résultat final.',
        'En production, libérer le plus gros en premier est la bonne pratique.',
    ], [
        'indent' => '  ',
        'title' => 'Conclusion du chapitre 9',
        'titleAlign' => 'center',
    ]);
    $ui->pressEnter();

    // ╔══════════════════════════════════════════════════════════════╗
    // ║  CHAPITRE 10 — Résultats finaux                             ║
    // ╚══════════════════════════════════════════════════════════════╝
    $ui->section('Chapitre 10 — Résultats finaux');

    if ($payload['processedCount'] < 3) {
        throw new RuntimeException("Pas assez d'images valides (>= 3 requis). Lancez setup.php.");
    }

    $results = $payload['topResults'];
    echo sprintf("\n  Images analysées : %d  |  ignorées : %d\n", $payload['processedCount'], $payload['skippedCount']);

    $ui->displayResults($results);

    $ui->pressEnter();

    // ╔══════════════════════════════════════════════════════════════╗
    // ║  CHAPITRE 11 — Nettoyage final & interprétation             ║
    // ╚══════════════════════════════════════════════════════════════╝
    $ui->section('Chapitre 11 — Nettoyage final & interprétation');

    $ui->withDots('  Nettoyage des variables restantes', 3, 400);
    unset($testImagePaths, $payload, $provider, $originImage, $results);
    $collected = gc_collect_cycles();
    if (function_exists('gc_mem_caches')) {
        gc_mem_caches();
    }
    echo sprintf("  Nettoyage effectué (cycles GC = %d)\n\n", $collected);

    $ui->printTextBox([
        'Interprétation :',
        '  • Si alloc n\'a pas bougé → c\'est la RÉSERVE PHP (pool).',
        '    PHP garde le « bureau » alloué pour éviter de redemander',
        '    de la mémoire à l\'OS. Ce n\'est PAS une fuite.',
        '',
        '  • Le pic (pic) ne redescend JAMAIS. C\'est le « high-water mark »,',
        '    le record historique. Normal et attendu.',
        '',
        '  • Si used a bien baissé → les objets ont été libérés correctement.',
        '    C\'est le résultat du reference counting + nos unset() explicites.',
        '',
        '  • Les quelques Ko résiduels = structures internes de PHP (tables',
        '    de symboles, autoloader cache, etc.). Inévitable et négligeable.',
    ], ['indent' => '  ']);

    // ╔══════════════════════════════════════════════════════════════╗
    // ║  CONCLUSION                                                  ║
    // ╚══════════════════════════════════════════════════════════════╝
    echo "\n" . str_repeat('═', 70) . "\n";

    $ui->printTextBox([
        'Ce que nous avons mis en place pour maîtriser la RAM :',
        '  ✓  Traitement en streaming (une image à la fois)',
        '  ✓  Libération immédiate de la ressource GD (Free GD)',
        '  ✓  unset() explicite des variables intermédiaires',
        '  ✓  gc_collect_cycles() en filet de sécurité (cycles)',
        '  ✓  Top 3 en mémoire constante (pas de tableau grandissant)',
        '  ✓  Séparation analyse / stockage (Value Objects légers)',
        '  ✓  Pas de tableau global accumulant toutes les images',
        '',
        'Ce que nous avons observé :',
        '  • L\'étape la plus coûteuse est TOUJOURS le Load GD (plusieurs Mo)',
        '  • Free GD libère quasi-intégralement cette mémoire',
        '  • unset() finalise le nettoyage des petits objets',
        '  • gc_collect_cycles() ne trouve rien : pas de cycles',
        '  • Le résidu par image (~3 Ko) = overhead PHP inévitable',
        '  • alloc reste stable : le pool PHP est réutilisé',
    ], [
        'indent' => '  ',
        'title' => 'CONCLUSION',
        'titleAlign' => 'center',
    ]);
    echo "\n" . str_repeat('─', 70) . "\n";
    echo sprintf("  Résultats visuels : http://localhost/%s/results.php\n", basename(__DIR__));
    echo sprintf("  Page interactive  : http://localhost/%s/public/index.php\n", basename(__DIR__));
    echo str_repeat('─', 70) . "\n\n";

    $ui->printTextBox([
        'Ce fichier a été conçu comme un mémento autant que comme un outil.',
        'Je sais qu\'il me sera utile quand je devrai à nouveau faire ',
        'du traitement d\'images en PHP. ',
        'En espérant qu\'il sera peut-être utile à d\'autres qu\'à moi !',
        '',
        'Margot Hourdillé',
        '',
        'PS : Du coup, Firstruner, verdict, challenge relevé ?',
    ], ['indent' => '  ', 'title' => 'Note personnelle']);
    
    echo $ui->signatureArt() . "\n";
    echo str_repeat('═', 100) . "\n\n";

} catch (Exception $e) {
    echo sprintf("\n  [ERREUR] %s\n           Fichier : %s:%d\n\n",
        $e->getMessage(), $e->getFile(), $e->getLine());
    exit(1);
}
