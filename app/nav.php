<?php
/**
 * Partial : barre de navigation du site.
 *
 * Attend la variable $activeNav ('setup', 'analysis', 'results')
 * et $baseUrlPath (chemin racine du projet).
 *
 * © 2026 Tous droits réservés.
 *
 * @author  Margot Hourdillé
 * @project RGBMatch — Challenge Firstruner
 * @version 1.0.0
 * @date    2026-03-11
 */

$setupUrl    = rtrim($baseUrlPath, '/') . '/public/setup';
$analysisUrl = rtrim($baseUrlPath, '/') . '/public/analyse';
$resultsUrl  = rtrim($baseUrlPath, '/') . '/public/results';
?>
    <nav class="site-nav">
        <a class="nav-brand" href="<?= htmlspecialchars($analysisUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">RGBMatch</a>
        <div class="nav-links">
            <a href="<?= htmlspecialchars($setupUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="nav-link<?= ($activeNav ?? '') === 'setup' ? ' active' : '' ?>">
                <span class="nav-icon">⚙</span> Initialisation
            </a>
            <a href="<?= htmlspecialchars($analysisUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="nav-link<?= ($activeNav ?? '') === 'analysis' ? ' active' : '' ?>">
                <span class="nav-icon">🔬</span> Analyse
            </a>
            <a href="<?= htmlspecialchars($resultsUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" class="nav-link<?= ($activeNav ?? '') === 'results' ? ' active' : '' ?>">
                <span class="nav-icon">🏆</span> Résultats
            </a>
        </div>
    </nav>
