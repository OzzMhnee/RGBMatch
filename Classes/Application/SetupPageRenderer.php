<?php
/**
 * Renderer HTML pour la page d'initialisation (setup).
 *
 * © 2026 Tous droits réservés.
 *
 * @author  Margot Hourdillé
 * @project RGBMatch — Challenge Firstruner
 * @version 1.0.0
 * @date    2026-03-11
 */

namespace RGBMatch\Application;

/**
 * Affiche le formulaire d'initialisation + aperçu des images déjà téléchargées.
 */
final class SetupPageRenderer
{
    /**
     * @param string   $baseUrlPath
     * @param string   $setupJsonUrl     Endpoint JSON pour lancer le setup
     * @param string   $originPath       Chemin fichier de l'image d'origine
     * @param bool     $originExists     L'image d'origine existe-t-elle ?
     * @param string[] $testImages       Liste des noms de fichiers test (basename)
     */
    public function render(
        string $baseUrlPath,
        string $setupJsonUrl,
        string $originPath,
        bool $originExists,
        array $testImages
    ): string {
        $layout  = new PublicPageLayoutRenderer();
        $testCount = count($testImages);

        ob_start();
        ?>
        <!-- ══ Section 1 : Formulaire d'initialisation ════════ -->
        <div class="page-section">
            <div class="sec-header reveal reveal-left">
                <span class="sec-arrow">→</span>
                <h2 class="sec-title">Initialisation des images</h2>
            </div>

            <div class="sec-body reveal">
                <section class="origin-section">
                    <div class="origin-info">
                        <?php
                        $hasAny        = $originExists || $testCount > 0;
                        $setupBtnLabel = $hasAny
                            ? 'Réinitialiser les images'
                            : 'Initialiser les images';
                        $setupNote = $hasAny
                            ? 'Cette action supprime les images existantes puis retélécharge depuis Unsplash.'
                            : 'Télécharge une image de référence + des images de test depuis Unsplash.';
                        ?>
                        <div class="note">
                            <strong><?= htmlspecialchars($setupNote, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></strong><br>
                            État actuel : origine <?= $originExists ? '✅' : '❌' ?> • tests : <strong><?= $testCount ?></strong> image(s).
                        </div>

                        <div class="note setup-form">
                            <div><strong>Combien d'images de test ?</strong> (min 6, max 30)</div>
                            <input id="setupCount" class="setup-input setup-count" type="number" min="6" max="30" value="10">
                            <div class="setup-field"><strong>Thème (query)</strong> (optionnel)</div>
                            <input id="setupQuery" class="setup-input setup-query" type="text" maxlength="60" placeholder="ex: nature, chat, city..." autocomplete="off" spellcheck="false">
                            <div class="note" style="margin-top:8px;">Caracteres autorises : lettres, chiffres, espaces, apostrophes et tirets. Maximum 5 mots.</div>
                        </div>

                        <div class="actions">
                            <button id="btnSetup" class="btn-primary" type="button"><?= htmlspecialchars($setupBtnLabel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></button>
                        </div>

                        <div id="setupStatus" class="status">En attente…</div>
                    </div>
                </section>
            </div>
        </div>

        <?php if ($originExists): ?>
        <!-- ══ Section 2 : Aperçu image de référence ══════════ -->
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
                            <p class="origin-title">Référence actuelle</p>
                            <div class="note"><strong>Fichier :</strong> <?= htmlspecialchars(basename($originPath), ENT_QUOTES, 'UTF-8') ?></div>
                        </div>
                    </div>
                </section>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($testCount > 0): ?>
        <!-- ══ Section 3 : Galerie des images de test ═════════ -->
        <div class="page-section">
            <div class="sec-header reveal reveal-left">
                <span class="sec-arrow">→</span>
                <h2 class="sec-title">Images de test (<?= $testCount ?>)</h2>
            </div>

            <div class="sec-body reveal">
                <section class="origin-section">
                    <div class="setup-gallery">
                        <?php foreach ($testImages as $filename): ?>
                        <div class="setup-thumb">
                            <img src="<?= $baseUrlPath ?>/storage/images/test/<?= htmlspecialchars($filename, ENT_QUOTES, 'UTF-8') ?>"
                                 alt="<?= htmlspecialchars($filename, ENT_QUOTES, 'UTF-8') ?>"
                                 loading="lazy">
                            <span class="setup-thumb-name"><?= htmlspecialchars($filename, ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            </div>
        </div>
        <?php endif; ?>
        <?php
        $mainContent = (string) ob_get_clean();

        return $layout->renderPage(
            $baseUrlPath,
            'RGBMatch — Initialisation',
            'setup',
            $mainContent,
            [
                'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap',
                'assets/shared/tokens.css',
                'assets/shared/layout.css',
                'assets/shared/origin.css',
                'assets/shared/buttons.css',
                'assets/shared/footer.css',
                'assets/index/setup.css',
            ],
            [
                'assets/shared/reveal.js',
                'assets/shared/utils.js',
                'assets/shared/setup.js',
            ],
            [
                'data-base-url'  => $baseUrlPath,
                'data-setup-url' => $setupJsonUrl,
            ]
        );
    }
}
