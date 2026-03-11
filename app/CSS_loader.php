<?php
/**
 * Helpers de chargement CSS avec versionnement local simple.
 *
 * @author  Margot Hourdillé
 * @project RGBMatch — Challenge Firstruner
 * @version 1.0.0
 * @date    2026-03-10
 * @update  2026-03-10
 */

/**
 * Construit l'URL publique d'un asset local ou externe.
 */
function app_asset_url(string $baseUrlPath, string $assetPath): string
{
    $assetPath = trim($assetPath);
    if ($assetPath === '') {
        return '';
    }

    if (preg_match('#^https?://#i', $assetPath) === 1) {
        return $assetPath;
    }

    $normalized = '/' . ltrim(str_replace('\\', '/', $assetPath), '/');
    $absolutePath = dirname(__DIR__) . str_replace('/', DIRECTORY_SEPARATOR, $normalized);
    $version = is_file($absolutePath) ? '?v=' . (string) filemtime($absolutePath) : '';

    return rtrim($baseUrlPath, '/') . $normalized . $version;
}

/**
 * @param array<int,string> $stylesheets
 */
function app_render_css_tags(string $baseUrlPath, array $stylesheets): string
{
    $tags = [];

    foreach ($stylesheets as $stylesheet) {
        if (!is_string($stylesheet) || trim($stylesheet) === '') {
            continue;
        }

        $href = app_asset_url($baseUrlPath, $stylesheet);
        if ($href === '') {
            continue;
        }

        $tags[] = '<link rel="stylesheet" href="' . htmlspecialchars($href, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">';
    }

    return $tags === [] ? '' : implode("\n", $tags) . "\n";
}