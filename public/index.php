<?php
/**
 * Front controller public : aiguille vers Setup, Analyse ou Résultats
 * selon le paramètre GET « page ».
 *
 * © 2026 Tous droits réservés.
 *
 * @author  Margot Hourdillé
 * @project RGBMatch — Challenge Firstruner
 * @version 2.0.0
 * @date    2026-03-11
 */

$allowedPages = ['analyse', 'results', 'setup'];
$page = 'analyse';

if (isset($_GET['page']) && is_string($_GET['page'])) {
    $requested = strtolower(trim($_GET['page']));
    if (in_array($requested, $allowedPages, true)) {
        $page = $requested;
    }
}

switch ($page) {
    case 'results':
        require __DIR__ . '/../results.php';
        break;

    case 'setup':
        require __DIR__ . '/setup.php';
        break;

    case 'analyse':
    default:
        require __DIR__ . '/analyse.php';
        break;
}
