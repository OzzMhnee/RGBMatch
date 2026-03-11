<?php
/**
 * Helpers de chargement JS avec versionnement local simple.
 *
 * @author  Margot Hourdillé
 * @project RGBMatch — Challenge Firstruner
 * @version 1.0.0
 * @date    2026-03-10
 * @update  2026-03-10
 */

/**
 * @param array<int,string> $scripts
 */
function app_render_js_tags(string $baseUrlPath, array $scripts): string
{
    $tags = [];

    foreach ($scripts as $script) {
        if (!is_string($script) || trim($script) === '') {
            continue;
        }

        $src = app_asset_url($baseUrlPath, $script);
        if ($src === '') {
            continue;
        }

        $tags[] = '<script src="' . htmlspecialchars($src, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" defer></script>';
    }

    return $tags === [] ? '' : implode("\n", $tags) . "\n";
}