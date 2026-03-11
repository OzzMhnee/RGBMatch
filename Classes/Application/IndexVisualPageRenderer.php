<?php
/**
 * Renderer HTML pour la page d'analyse interactive.
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

/**
 * Renderer HTML pour la version web "visuelle" de l'analyse (interactive).
 * SRP: rendu/HTML uniquement.
 */
final class IndexVisualPageRenderer
{
    /**
     * Page "shell" : affiche l'image de référence + bouton de démarrage + zone d'animation.
     * Les données de run sont récupérées en JSON.
     */
    public function renderShell(
        string $baseUrlPath,
        string $originPath,
        string $runJsonUrl,
        string $resultsUrl,
        string $setupJsonUrl = '',
        bool $originExists = true,
        int $testCount = 0
    ): string
    {
        $layout = new PublicPageLayoutRenderer();
        $setupPageUrl = rtrim($baseUrlPath, '/') . '/public/setup';

        ob_start();
        ?>

        <?php if ($originExists): ?>
        <!-- ══ Section 1 : Image de référence ═════════════════ -->
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
                            <p class="origin-title">Référence de comparaison</p>
                            <div class="note">Cette image sert de base pour le classement. Chaque image de test est comparée à elle sur ses composantes R, G, B (%).</div>
                            <div class="note" style="margin-top:8px;"><strong>Fichier :</strong> <?= htmlspecialchars(basename($originPath), ENT_QUOTES, 'UTF-8') ?></div>
                        </div>
                    </div>
                </section>
            </div>
        </div>
        <!-- ══ Section 1.5 : Top départ ═══════════════════════ -->
        <div class="page-section">
            <div class="sec-header reveal reveal-left">
                <span class="sec-arrow">→</span>
                <h2 class="sec-title">Top départ</h2>
            </div>

            <div class="sec-body reveal">
                <section class="origin-section">
                    <div class="origin-info">
                        <?php if ($testCount >= 6): ?>
                        <div class="note">Clique sur <strong>Top départ</strong> pour lancer l'analyse complète : <?= (int) $testCount ?> image(s) de test → classement RGB → Top 3.</div>
                        <?php else: ?>
                        <div class="note"><strong>Pas assez d'images de test</strong> (<?= (int) $testCount ?>/6 minimum). <a href="<?= htmlspecialchars($setupPageUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">Initialiser les images</a> d'abord.</div>
                        <?php endif; ?>
                        <div class="actions">
                            <button id="btnStart" class="btn-primary" type="button" <?= $testCount >= 6 ? '' : 'disabled' ?>>Top départ</button>
                            <a class="btn-link" href="<?= htmlspecialchars($resultsUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">Voir résultats</a>
                        </div>
                        <div id="status" class="status">En attente…</div>
                        <div class="progress-bar-outer" id="progressOuter" style="display:none;"><div class="progress-bar-inner" id="progressInner"></div></div>
                    </div>
                </section>
            </div>
        </div>

        <?php else: ?>
        <!-- ══ Aucune image : message d'accueil ═══════════════ -->
        <div class="page-section">
            <div class="sec-header reveal reveal-left">
                <span class="sec-arrow">→</span>
                <h2 class="sec-title">Bienvenue</h2>
            </div>

            <div class="sec-body reveal">
                <section class="origin-section">
                    <div class="origin-info">
                        <div class="note">
                            <strong>Aucune image n'a encore été téléchargée.</strong><br>
                            Pour commencer, rendez-vous sur la page
                            <a href="<?= htmlspecialchars($setupPageUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">Initialisation</a>
                            afin de télécharger une image de référence et des images de test depuis Unsplash.
                        </div>
                        <div class="actions" style="margin-top:16px;">
                            <a class="btn-primary" href="<?= htmlspecialchars($setupPageUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">Initialiser les images</a>
                        </div>
                    </div>
                </section>
            </div>
        </div>
        <!-- Éléments cachés requis par le JS -->
        <div style="display:none;">
            <button id="btnStart" type="button" disabled></button>
            <div id="status"></div>
            <div id="progressOuter"><div id="progressInner"></div></div>
        </div>
        <?php endif; ?>

        <?php if ($originExists && $testCount >= 3): ?>
        <!-- ══ Section 2 : Tri Top 3 (cachée initialement, révélée par JS) ═══ -->
        <div class="page-section" id="triSection" style="display:none;">
            <div class="sec-header reveal reveal-left">
                <span class="sec-arrow">→</span>
                <h2 class="sec-title">Tri Top 3</h2>
            </div>

            <div class="sec-body reveal">
                <section class="tri-section">
                    <div class="note">Chaque image est analysée (R G B %) puis classée par similarité. Elle rejoint le Top 3 ou est détruite (animation).</div>

                    <div class="tri-grid">
                        <div class="top3-panel">
                            <div class="panel-title">Top 3</div>
                            <div class="top3-slots" id="top3Slots">
                                <div class="top3-slot">
                                    <div class="rank-badge">#1</div>
                                    <img id="topImg1" class="slot-img" alt="#1" />
                                </div>
                                <div class="top3-slot">
                                    <div class="rank-badge">#2</div>
                                    <img id="topImg2" class="slot-img" alt="#2" />
                                </div>
                                <div class="top3-slot">
                                    <div class="rank-badge">#3</div>
                                    <img id="topImg3" class="slot-img" alt="#3" />
                                </div>
                            </div>
                        </div>

                        <div class="right-panel">
                            <div class="calc-panel">
                                <div class="panel-title">Calcul R G B %</div>
                                <div class="step-line">
                                    <span id="stepName">—</span>
                                    <span id="stepExplain" class="muted">—</span>
                                </div>

                                <div class="current-image" id="currentBox">
                                    <div class="scan-line" id="scanLine"></div>
                                    <div class="img-counter" id="imgCounter" style="display:none;"></div>
                                    <img id="currentImg" alt="Image en cours" style="display:none;">
                                    <div id="currentPlaceholder" class="placeholder">Aucune image pour le moment</div>
                                    <div id="badge" class="badge" style="display:none;"></div>
                                </div>
                                <div id="currentMeta" class="current-meta"></div>

                                <div class="rgb-metric-row">
                                    <div class="rgb-metric-item">
                                        <div class="rgb-metric-head">
                                            <span class="rgb-lbl rgb-lbl-r">R</span>
                                            <span id="valR" class="rgb-metric-val">—</span>
                                        </div>
                                        <div class="bar-container"><div id="barR" class="bar-fill bar-red" style="width:0%"></div></div>
                                    </div>
                                    <div class="rgb-metric-item">
                                        <div class="rgb-metric-head">
                                            <span class="rgb-lbl rgb-lbl-g">G</span>
                                            <span id="valG" class="rgb-metric-val">—</span>
                                        </div>
                                        <div class="bar-container"><div id="barG" class="bar-fill bar-green" style="width:0%"></div></div>
                                    </div>
                                    <div class="rgb-metric-item">
                                        <div class="rgb-metric-head">
                                            <span class="rgb-lbl rgb-lbl-b">B</span>
                                            <span id="valB" class="rgb-metric-val">—</span>
                                        </div>
                                        <div class="bar-container"><div id="barB" class="bar-fill bar-blue" style="width:0%"></div></div>
                                    </div>
                                </div>
                            </div>

                            <div class="tests-bin">
                                <div class="bin-title">images tests</div>
                                <div id="testsBin" class="bin-grid"></div>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </div>

        <!-- ══ Section 3 : Détails (cachée initialement, révélée par JS) ══ -->
        <div class="page-section" id="detailsSection" style="display:none;">
            <div class="sec-header reveal reveal-left">
                <span class="sec-arrow">→</span>
                <h2 class="sec-title">Détails — deltas RAM + temps</h2>
            </div>

            <div class="sec-body reveal">
                <section class="origin-section">
                    <div class="note">Pour chaque image analysée, on récapitule les deltas mémoire (used / alloc / pic) et le temps par étape.</div>
                    <div id="detailsWrap" class="details-wrap"></div>
                </section>
            </div>
        </div>
        <?php else: ?>
        <!-- Éléments cachés requis par le JS quand les sections Tri/Détails ne sont pas rendues -->
        <div style="display:none;">
            <div id="top3Slots">
                <img id="topImg1" alt="" /><img id="topImg2" alt="" /><img id="topImg3" alt="" />
            </div>
            <div id="currentBox">
                <div id="scanLine"></div>
                <div id="imgCounter"></div>
                <img id="currentImg" alt="">
                <div id="currentPlaceholder"></div>
                <div id="badge"></div>
            </div>
            <div id="currentMeta"></div>
            <span id="stepName"></span><span id="stepExplain"></span>
            <span id="valR"></span><span id="valG"></span><span id="valB"></span>
            <div id="barR"></div><div id="barG"></div><div id="barB"></div>
            <div id="testsBin"></div>
            <div id="detailsWrap"></div>
        </div>
        <?php endif; ?>
        <?php
        $mainContent = (string) ob_get_clean();

        return $layout->renderPage(
            $baseUrlPath,
            'RGBMatch — Analyse interactive',
            'analysis',
            $mainContent,
            [
                'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap',
                'assets/shared/tokens.css',
                'assets/shared/layout.css',
                'assets/shared/origin.css',
                'assets/shared/bars.css',
                'assets/shared/buttons.css',
                'assets/shared/footer.css',
                'assets/index/setup.css',
                'assets/index/tri.css',
                'assets/index/details.css',
            ],
            [
                'assets/shared/reveal.js',
                'assets/shared/utils.js',
                'assets/index/animation.js',
                'assets/index/app.js',
            ],
            [
                'data-base-url' => $baseUrlPath,
                'data-run-url' => $runJsonUrl,
                'data-setup-url' => $setupJsonUrl,
            ]
        );
    }
}
