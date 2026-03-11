<?php
/**
 * Renderer HTML pour la page de résultats.
 *
 * © 2026 Tous droits réservés.
 *
 * @author  Margot Hourdillé
 * @project RGBMatch — Challenge Firstruner
 * @version 2.0.0
 * @date    2026-03-06
 * @update  2026-03-11
 */

namespace RGBMatch\Application;

use RGBMatch\Metier\CImageData;

/**
 * Renderer HTML pour la page de résultats (SRP: rendu uniquement).
 */
final class ResultsPageRenderer
{
    /**
     * @param array<int, object> $topResults objets ayant une méthode toArray()
     * @param int $totalAnalyzed Nombre total d'images de test analysées
     */
    public function render(string $baseUrlPath, string $originPath, CImageData $originImage, array $topResults, int $totalAnalyzed = 0): string
    {
        $layout = new PublicPageLayoutRenderer();

        ob_start();
        ?>
        <!-- ── Section : image de référence ───────────────── -->
        <div class="page-section">
            <div class="sec-header reveal reveal-left">
                <span class="sec-arrow">→</span>
                <h2 class="sec-title">Image de référence</h2>
            </div>

            <div class="sec-body reveal">
                <section class="origin-section">
                    <div class="origin-content">
                        <div class="origin-image">
                            <img src="<?= $baseUrlPath ?>/storage/images/<?= htmlspecialchars(basename($originPath), ENT_QUOTES, 'UTF-8') ?>" alt="Image d'origine">
                        </div>
                        <div class="origin-info">
                            <p class="origin-title">Image analysée comme référence</p>
                            <?php $rgb = $originImage->getRgbPercentage(); ?>

                            <div class="run-summary">
                                <span>Fichier :</span>
                                <strong><?= htmlspecialchars(basename($originPath), ENT_QUOTES, 'UTF-8') ?></strong>
                                <?php if ($totalAnalyzed > 0): ?>
                                <span class="run-chip">🖼 <?= (int) $totalAnalyzed ?> image(s) analysée(s)</span>
                                <span class="run-chip">🏆 Top <?= count($topResults) ?> conservé(s)</span>
                                <?php endif; ?>
                            </div>

                            <div class="rgb-bar">
                                <div class="rgb-label">
                                    <span>Rouge</span>
                                    <span><?= number_format($rgb->getRed(), 2) ?>%</span>
                                </div>
                                <div class="bar-container">
                                    <div class="bar-fill bar-red" style="width: <?= $rgb->getRed() ?>%">
                                        <?= number_format($rgb->getRed(), 1) ?>%
                                    </div>
                                </div>
                            </div>

                            <div class="rgb-bar">
                                <div class="rgb-label">
                                    <span>Vert</span>
                                    <span><?= number_format($rgb->getGreen(), 2) ?>%</span>
                                </div>
                                <div class="bar-container">
                                    <div class="bar-fill bar-green" style="width: <?= $rgb->getGreen() ?>%">
                                        <?= number_format($rgb->getGreen(), 1) ?>%
                                    </div>
                                </div>
                            </div>

                            <div class="rgb-bar">
                                <div class="rgb-label">
                                    <span>Bleu</span>
                                    <span><?= number_format($rgb->getBlue(), 2) ?>%</span>
                                </div>
                                <div class="bar-container">
                                    <div class="bar-fill bar-blue" style="width: <?= $rgb->getBlue() ?>%">
                                        <?= number_format($rgb->getBlue(), 1) ?>%
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </div>

        <!-- ── Section : Top 3 ────────────────────────────── -->
        <div class="page-section">
            <div class="sec-header reveal reveal-left">
                <span class="sec-arrow">→</span>
                <h2 class="sec-title">Top 3 des images les plus similaires</h2>
            </div>

            <div class="sec-body reveal results-section">
                <div class="winners-grid">
                    <?php foreach ($topResults as $index => $result): ?>
                        <?php
                            if (!is_object($result) || !method_exists($result, 'toArray')) {
                                continue;
                            }
                            $data      = $result->toArray();
                            $rankClass = 'rank-' . ($index + 1);
                            $rankText  = '#' . ($index + 1);
                        ?>
                        <div class="winner-card">
                            <div class="rank-badge <?= $rankClass ?>">
                                <?= $rankText ?>
                            </div>
                            <img src="<?= $baseUrlPath ?>/storage/images/test/<?= htmlspecialchars((string) ($data['filename'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                 alt="Image gagnante #<?= $index + 1 ?>"
                                 class="winner-image">
                            <div class="winner-info">
                                <div class="similarity-score">
                                    <?= number_format((float) ($data['similarity'] ?? 0), 2) ?>%
                                </div>
                                <div class="filename">
                                    <?= htmlspecialchars((string) ($data['filename'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                </div>

                                <div class="rgb-values">
                                    <div class="rgb-value red">
                                        R<br><?= number_format((float) ($data['rgb']['r'] ?? 0), 1) ?>%
                                    </div>
                                    <div class="rgb-value green">
                                        G<br><?= number_format((float) ($data['rgb']['g'] ?? 0), 1) ?>%
                                    </div>
                                    <div class="rgb-value blue">
                                        B<br><?= number_format((float) ($data['rgb']['b'] ?? 0), 1) ?>%
                                    </div>
                                </div>

                                <div class="differences">
                                    <div class="diff-label">Différences avec l'origine</div>
                                    <div class="diff-values">
                                        <span>R: <?= number_format((float) ($data['diff']['r'] ?? 0), 2) ?></span>
                                        <span>G: <?= number_format((float) ($data['diff']['g'] ?? 0), 2) ?></span>
                                        <span>B: <?= number_format((float) ($data['diff']['b'] ?? 0), 2) ?></span>
                                        <strong>Total: <?= number_format((float) ($data['diff']['total'] ?? 0), 2) ?></strong>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php
        $mainContent = (string) ob_get_clean();

        return $layout->renderPage(
            $baseUrlPath,
            'RGBMatch — Résultats',
            'results',
            $mainContent,
            [
                'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap',
                'assets/shared/tokens.css',
                'assets/shared/layout.css',
                'assets/shared/origin.css',
                'assets/shared/bars.css',
                'assets/shared/footer.css',
                'assets/results/winners.css',
            ],
            [
                'assets/shared/reveal.js',
            ]
        );
    }
}
