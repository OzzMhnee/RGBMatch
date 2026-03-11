<?php
/**
 * Layout HTML partagé pour les pages web publiques.
 *
 * © 2026 Tous droits réservés.
 *
 * @author  Margot Hourdillé
 * @project RGBMatch — Challenge Firstruner
 * @version 2.0.0
 * @date    2026-03-10
 * @update  2026-03-11
 */

namespace RGBMatch\Application;

final class PublicPageLayoutRenderer
{
    /**
     * @param array<int,string> $stylesheets
     * @param array<int,string> $scripts
     * @param array<string,string> $bodyAttributes
     */
    public function renderPage(
        string $baseUrlPath,
        string $title,
        string $activeNav,
        string $mainContent,
        array $stylesheets,
        array $scripts = [],
        array $bodyAttributes = []
    ): string {
        ob_start();
        ?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<?= app_render_css_tags($baseUrlPath, $stylesheets) ?></head>
<body<?= $this->renderBodyAttributes($bodyAttributes) ?>>
    <?php $this->renderNav($baseUrlPath, $activeNav); ?>
    <div class="container">
        <main class="site-main">
<?= $mainContent ?>
        </main>
        <?php $this->renderFooter($baseUrlPath); ?>
    </div>
<?= app_render_js_tags($baseUrlPath, $scripts) ?></body>
</html>
        <?php

        return (string) ob_get_clean();
    }

    /**
     * @param array<string,string> $attributes
     */
    private function renderBodyAttributes(array $attributes): string
    {
        if ($attributes === []) {
            return '';
        }

        $chunks = [];
        foreach ($attributes as $name => $value) {
            if (!is_string($name) || $name === '') {
                continue;
            }

            $chunks[] = ' ' . htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                . '="' . htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
        }

        return implode('', $chunks);
    }

    private function renderNav(string $baseUrlPath, string $activeNav): void
    {
        require dirname(__DIR__, 2) . '/app/nav.php';
    }

    private function renderFooter(string $baseUrlPath = ''): void
    {
        require dirname(__DIR__, 2) . '/app/footer.php';
    }
}