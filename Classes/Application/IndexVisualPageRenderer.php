<?php
/**
 * Renderer HTML pour la page d'analyse interactive.
 *
 * @author  Margot Hourdillé
 * @project RGBMatch — Challenge Firstruner
 * @version 1.0.0
 * @date    2026-03-06
 * @update  2026-03-06
 */

namespace RGBMatch\Application;

/**
 * Renderer HTML pour la version web "visuelle" de index.php (interactive).
 * SRP: rendu/HTML uniquement.
 */
final class IndexVisualPageRenderer
{
    /**
     * Page "shell" : affiche l'image de référence + bouton de démarrage + zone d'animation.
     * Les données de run sont récupérées en JSON depuis public/index.php.
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
        ob_start();
        $hasAnyImages = ($originExists || $testCount > 0);
        $setupBtnLabel = $hasAnyImages ? 'Réinitialiser les images (setup)' : 'Initialiser les images (setup)';
        $setupNote = $hasAnyImages
            ? 'Cette action supprime les images existantes puis retélécharge depuis Unsplash.'
            : 'Télécharge une image de référence + des images de test depuis Unsplash.';
        ?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RGBMatch — Analyse interactive</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= $baseUrlPath ?>/assets/shared/tokens.css">
    <link rel="stylesheet" href="<?= $baseUrlPath ?>/assets/shared/layout.css">
    <link rel="stylesheet" href="<?= $baseUrlPath ?>/assets/shared/origin.css">
    <link rel="stylesheet" href="<?= $baseUrlPath ?>/assets/shared/bars.css">
    <link rel="stylesheet" href="<?= $baseUrlPath ?>/assets/shared/buttons.css">
    <link rel="stylesheet" href="<?= $baseUrlPath ?>/assets/shared/footer.css">
    <link rel="stylesheet" href="<?= $baseUrlPath ?>/assets/index/setup.css">
    <link rel="stylesheet" href="<?= $baseUrlPath ?>/assets/index/tri.css">
    <link rel="stylesheet" href="<?= $baseUrlPath ?>/assets/index/details.css">
</head>
<body>

    <!-- ── Barre de navigation fixe ───────────────────────── -->
    <nav class="site-nav">
        <a class="nav-brand" href="<?= htmlspecialchars($baseUrlPath, ENT_QUOTES, 'UTF-8') ?>/public/index.php">RGBMatch</a>
        <div class="nav-links">
            <a href="<?= htmlspecialchars($baseUrlPath, ENT_QUOTES, 'UTF-8') ?>/public/index.php" class="active">Analyse</a>
            <a href="<?= htmlspecialchars($resultsUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">Résultats</a>
        </div>
    </nav>

    <div class="container">

        <!-- ══ Section 1 : Initialisation (toujours visible) ══ -->
        <div class="page-section">
            <div class="sec-header reveal reveal-left">
                <span class="sec-arrow">→</span>
                <h2 class="sec-title">Initialisation des images</h2>
            </div>

            <div class="sec-body reveal">
                <section class="origin-section">
                    <div class="origin-info">
                        <div class="note">
                            <strong><?= htmlspecialchars($setupNote, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></strong><br>
                            État actuel : origine <?= $originExists ? '✅' : '❌' ?> • tests : <strong><?= (int) $testCount ?></strong> image(s).
                        </div>

                        <div class="actions">
                            <button id="btnSetup" class="btn-primary" type="button"><?= htmlspecialchars($setupBtnLabel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></button>
                        </div>

                        <div class="note setup-form">
                            <div><strong>Combien d'images de test ?</strong> (min 6, max 30)</div>
                            <input id="setupCount" class="setup-input setup-count" type="number" min="6" max="30" value="10">
                            <div class="setup-field"><strong>Thème (query)</strong> (optionnel)</div>
                            <input id="setupQuery" class="setup-input setup-query" type="text" placeholder="ex: nature, chat, city...">
                        </div>

                        <div id="setupStatus" class="status">En attente…</div>
                    </div>
                </section>
            </div>
        </div>

        <?php if ($originExists): ?>
        <!-- ══ Section 2 : Image de référence ═════════════════ -->
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
        <!-- ══ Section 2.5 : Top départ ═══════════════════════ -->
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
                        <div class="note"><strong>Pas assez d'images de test</strong> (<?= (int) $testCount ?>/6 minimum). Lance d'abord l'initialisation ci-dessus.</div>
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
        <!-- Éléments cachés requis par le JS quand il n'y a pas d'image d'origine -->
        <div style="display:none;">
            <button id="btnStart" type="button" disabled></button>
            <div id="status"></div>
            <div id="progressOuter"><div id="progressInner"></div></div>
        </div>
        <?php endif; ?>

        <?php if ($originExists && $testCount >= 3): ?>
        <!-- ══ Section 3 : Tri Top 3 (cachée initalement, révélée par JS) ═══ -->
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

        <!-- ══ Section 4 : Détails (cachée initialement, révélée par JS) ══ -->
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

        <!-- ── Footer ─────────────────────────────────────── -->
        <footer class="site-footer">
            <div>
                Réalisé par
                <a href="https://github.com/OzzMhnee" target="_blank" rel="noopener">OzzMhnee (Margot Hourdillé)</a>
                <span class="footer-sep">•</span>
                Challenge proposé par
                <a href="https://gitlab.com/firstruner" target="_blank" rel="noopener">Firstruner</a>
            </div>
            <div>Architecture SOLID • Factory/Builder Pattern • DI • RAM memory management</div>
        </footer>

    </div>

<script>
(() => {
    const RUN_URL = <?= json_encode($runJsonUrl, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
    const SETUP_URL = <?= json_encode($setupJsonUrl, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
    const BASE = <?= json_encode($baseUrlPath, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;

    const btn = document.getElementById('btnStart');
    const status = document.getElementById('status');
    const btnSetup = document.getElementById('btnSetup');
    const setupStatus = document.getElementById('setupStatus');
    const setupCount = document.getElementById('setupCount');
    const setupQuery = document.getElementById('setupQuery');
    const progressOuter = document.getElementById('progressOuter');
    const progressInner = document.getElementById('progressInner');
    const currentBox = document.getElementById('currentBox');
    const currentImg = document.getElementById('currentImg');
    const placeholder = document.getElementById('currentPlaceholder');
    const badge = document.getElementById('badge');
    const scanLine = document.getElementById('scanLine');
    const imgCounter = document.getElementById('imgCounter');
    const currentMeta = document.getElementById('currentMeta');
    const detailsWrap = document.getElementById('detailsWrap');

    const stepName = document.getElementById('stepName');
    const stepExplain = document.getElementById('stepExplain');

    const valR = document.getElementById('valR');
    const valG = document.getElementById('valG');
    const valB = document.getElementById('valB');
    const barR = document.getElementById('barR');
    const barG = document.getElementById('barG');
    const barB = document.getElementById('barB');
    const testsBin = document.getElementById('testsBin');
    const topImg1 = document.getElementById('topImg1');
    const topImg2 = document.getElementById('topImg2');
    const topImg3 = document.getElementById('topImg3');

    // Reproduit exactement BytesFormatter::format() + signe cli
    // round($v, 2) en PHP donne '2' pour 2.0 et '1.5' pour 1.5 (pas de zéros trailing).
    const fmtBytes = (n) => {
        if (n === 0) return '0 B';
        const abs = Math.abs(n);
        const sign = n < 0 ? '-' : '+';
        const units = ['B', 'KB', 'MB', 'GB'];
        let v = abs;
        let u = 0;
        while (v >= 1024 && u < units.length - 1) { v /= 1024; u++; }
        // parseFloat(toFixed(2)) -> supprime les zéros trailing comme PHP round()
        const s = u === 0 ? String(Math.round(v)) : String(parseFloat(v.toFixed(2)));
        return `${sign}${s} ${units[u]}`;
    };

    // Reproduit exactement ConsoleRenderer::formatDuration() :
    // < 1000 ms -> '42 ms', >= 1000 ms -> '1.234 s'
    const fmtMs = (n) => {
        const ms = Number.isFinite(n) ? Math.max(0, n) : 0;
        if (ms >= 1000) {
            return (ms / 1000).toFixed(3) + ' s';
        }
        return `${Math.round(ms)} ms`;
    };

    const setRgbDisplay = (rgb) => {
        if (!rgb) return;
        const r = Number(rgb.r) || 0;
        const g = Number(rgb.g) || 0;
        const b = Number(rgb.b) || 0;
        valR.textContent = r.toFixed(2) + '%';
        valG.textContent = g.toFixed(2) + '%';
        valB.textContent = b.toFixed(2) + '%';
        barR.style.width = Math.min(100, r).toFixed(2) + '%';
        barG.style.width = Math.min(100, g).toFixed(2) + '%';
        barB.style.width = Math.min(100, b).toFixed(2) + '%';
    };

    const clearRgbDisplay = () => {
        [valR, valG, valB].forEach((el) => { el.textContent = '—'; });
        [barR, barG, barB].forEach((el) => { el.style.width = '0%'; });
    };

    const clearTop3 = () => {
        topImg1.removeAttribute('src');
        topImg2.removeAttribute('src');
        topImg3.removeAttribute('src');
    };

    const setTop3FromArray = (arr) => {
        const items = arr || [];
        const setOrClear = (imgEl, item) => {
            if (item && item.filename) {
                imgEl.src = `${BASE}/storage/images/test/${encodeURIComponent(item.filename)}`;
            } else {
                imgEl.removeAttribute('src');
            }
        };
        setOrClear(topImg1, items[0]);
        setOrClear(topImg2, items[1]);
        setOrClear(topImg3, items[2]);
    };

    const top3Filenames = (arr) => {
        const items = arr || [];
        return [
            items[0]?.filename || null,
            items[1]?.filename || null,
            items[2]?.filename || null,
        ];
    };

    const setTop3FromFilenames = (files) => {
        const mapped = (files || []).slice(0, 3).map((f) => (f ? { filename: f } : null));
        while (mapped.length < 3) mapped.push(null);
        setTop3FromArray(mapped);
    };

    const frameElFor = (el) => (
        el?.closest?.('.top3-slot') ||
        el?.closest?.('.current-image') ||
        el?.closest?.('.bin-item') ||
        el
    );

    const applyCloneFrameStyleFromTarget = (clone, targetEl) => {
        const frame = frameElFor(targetEl);
        if (!frame) return;
        const cs = getComputedStyle(frame);
        // Contrainte: aucun border-radius tout du long
        clone.style.borderRadius = '0';
        if (cs.border && cs.border !== '0px none rgb(0, 0, 0)') clone.style.border = cs.border;
        if (cs.boxShadow && cs.boxShadow !== 'none') clone.style.boxShadow = cs.boxShadow;
        clone.style.background = 'transparent';
    };

    const createFlyClone = (imgEl, fromRect, toRect) => {
        const clone = imgEl.cloneNode(true);

        // Contrainte UX: pas de resize (pas de scale animé).
        // On crée directement le clone à la taille d'arrivée, puis on ne fait que translate.
        const fromCx = fromRect.left + fromRect.width / 2;
        const fromCy = fromRect.top + fromRect.height / 2;

        clone.style.position = 'fixed';
        clone.style.width = `${toRect.width}px`;
        clone.style.height = `${toRect.height}px`;
        clone.style.left = `${fromCx - toRect.width / 2}px`;
        clone.style.top = `${fromCy - toRect.height / 2}px`;
        clone.style.objectFit = 'cover';
        clone.style.zIndex = '9999';
        clone.style.borderRadius = '0';
        clone.style.border = '1px solid var(--border)';
        clone.style.boxShadow = '0 20px 50px rgba(0,0,0,0.30)';
        clone.style.transformOrigin = 'center center';
        clone.style.transition = 'transform 600ms cubic-bezier(0.22, 1, 0.36, 1), opacity 600ms ease, box-shadow 600ms ease, border-color 600ms ease';
        clone.style.opacity = '1';
        clone.style.willChange = 'transform, opacity';
        document.body.appendChild(clone);
        return clone;
    };

    // ────────────────────────────────────────────────────────────
    // Setup (initialisation / réinitialisation) — asynchrone
    // ────────────────────────────────────────────────────────────
    const setSetupState = (kind, msg) => {
        setupStatus.className = 'status' + (kind ? ` ${kind}` : '');
        setupStatus.textContent = msg;
    };

    if (btnSetup) {
        btnSetup.addEventListener('click', async () => {
            const count = parseInt(setupCount?.value || '10', 10);
            const query = String(setupQuery?.value || '').trim();

            if (!Number.isFinite(count) || count < 6 || count > 30) {
                setSetupState('error', "Nombre d'images invalide (min 6, max 30)." );
                return;
            }

            btnSetup.disabled = true;
            if (btn) btn.disabled = true;
            setSetupState('running', 'Téléchargement en cours… (Unsplash)');

            try {
                const res = await fetch(SETUP_URL, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ count, query }),
                });

                const data = await res.json().catch(() => null);
                if (!res.ok) {
                    const msg = data?.message || `Erreur HTTP ${res.status}`;
                    throw new Error(msg);
                }

                const seconds = data?.result?.seconds;
                const downloaded = data?.result?.downloadedTests;
                const s = Number.isFinite(seconds) ? `${seconds.toFixed(2)}s` : '';
                setSetupState('done', `Setup terminé. Tests téléchargés: ${downloaded}. ${s}`);

                // Recharge pour afficher l'origine + les tests
                setTimeout(() => window.location.reload(), 800);
            } catch (e) {
                setSetupState('error', `Échec du setup : ${e?.message || e}`);
                btnSetup.disabled = false;
                if (btn) btn.disabled = false;
            }
        });
    }

    const flyTo = async (fromImgEl, toImgEl, hideFrom = false) => {
        const fromRect = fromImgEl.getBoundingClientRect();
        const toRect = toImgEl.getBoundingClientRect();
        const clone = createFlyClone(fromImgEl, fromRect, toRect);

        const previousVisibility = fromImgEl.style.visibility;
        try {
            applyCloneFrameStyleFromTarget(clone, toImgEl);
            if (hideFrom) fromImgEl.style.visibility = 'hidden';

            // Départ du clone: centré sur l'image source, à la taille cible.
            // Mouvement: translate uniquement (pas de scale) pour éviter tout resize.
            const startLeft = parseFloat(clone.style.left) || 0;
            const startTop = parseFloat(clone.style.top) || 0;
            const dx = toRect.left - startLeft;
            const dy = toRect.top - startTop;

            // force reflow
            clone.getBoundingClientRect();
            clone.style.transform = `translate(${dx}px, ${dy}px)`;
            await sleep(620);
        } finally {
            clone.remove();
            if (hideFrom) fromImgEl.style.visibility = previousVisibility;
        }
    };

    const fadeOutEl = async (el, ms = 360) => {
        el.classList.add('fade-out');
        await sleep(ms);
        el.classList.remove('fade-out');
    };

    const animateDestroy = async () => {
        scanLine.classList.remove('scanning');
        currentImg.classList.add('drop-animation');
        await sleep(600);
        currentImg.classList.remove('drop-animation');
        currentImg.style.display = 'none';
        currentImg.removeAttribute('src');
        currentImg.style.visibility = '';
        placeholder.style.display = 'block';
    };

    const renderTestsBin = (images) => {
        testsBin.innerHTML = '';
        (images || []).forEach((img) => {
            const cell = document.createElement('div');
            cell.className = 'bin-item';
            cell.dataset.filename = img.filename;

            // L'image est volontairement invisible dans la boîte (contrainte UX),
            // mais présente dans le DOM pour servir de source au clone (vol).
            const hiddenImg = document.createElement('img');
            hiddenImg.className = 'bin-thumb';
            hiddenImg.alt = img.filename;
            hiddenImg.dataset.filename = img.filename;
            hiddenImg.src = `${BASE}/storage/images/test/${encodeURIComponent(img.filename)}`;
            cell.appendChild(hiddenImg);
            testsBin.appendChild(cell);
        });
    };

    const getThumbFor = (filename) => testsBin.querySelector(`img.bin-thumb[data-filename="${CSS.escape(filename)}"]`);
    const getBinCellFor = (filename) => testsBin.querySelector(`.bin-item[data-filename="${CSS.escape(filename)}"]`);

    const withSrcClass = (imgEl) => {
        if (imgEl.getAttribute('src')) imgEl.classList.add('has-src');
        else imgEl.classList.remove('has-src');
    };

    const refreshSlotVisibility = () => {
        withSrcClass(topImg1);
        withSrcClass(topImg2);
        withSrcClass(topImg3);
        [topImg1, topImg2, topImg3].forEach((el) => {
            const slot = el.closest('.top3-slot');
            if (!slot) return;
            if (el.getAttribute('src')) {
                slot.classList.remove('slot-empty');
                slot.classList.add('slot-filled');
            } else {
                slot.classList.add('slot-empty');
                slot.classList.remove('slot-filled');
            }
        });
    };

    const slotElForRank = (rankIndex) => (rankIndex === 0 ? topImg1 : (rankIndex === 1 ? topImg2 : topImg3));

    const animateTop3Transition = async (prevTop3, nextTop3, currentImageEl) => {
        // Objectif UX: séquence stable
        // 1) #3 sort (si besoin)
        // 2) #2 -> #3
        // 3) #1 -> #2
        // 4) insertion de l'image courante au bon rang

        const prev = top3Filenames(prevTop3);
        const next = top3Filenames(nextTop3);

        const inserted = next.find((f) => f && !prev.includes(f));
        if (!inserted) {
            // Pas d'insertion : applique l'état final directement (pas d'effet de range)
            setTop3FromFilenames(next);
            refreshSlotVisibility();
            return;
        }

        const insertedRank = next.indexOf(inserted);
        const leaving = prev.find((f) => f && !next.includes(f));

        // 1) fade-out de celui qui sort du Top3
        if (leaving) {
            const leavingRank = prev.indexOf(leaving);
            const leavingEl = slotElForRank(leavingRank);
            await fadeOutEl(leavingEl, 320);
            prev[leavingRank] = null;
            setTop3FromFilenames(prev);
            refreshSlotVisibility();
        }

        // 2) Déplacements "descendants" pour faire de la place
        // On part du bas pour éviter les collisions visuelles.
        for (let targetRank = 2; targetRank > insertedRank; targetRank--) {
            const shouldBeThere = next[targetRank];
            const fromRank = targetRank - 1;
            const moving = prev[fromRank];

            if (!shouldBeThere || !moving) continue;
            if (moving !== shouldBeThere) continue;

            const fromEl = slotElForRank(fromRank);
            const toEl = slotElForRank(targetRank);
            if (fromEl.getAttribute('src')) {
                await flyTo(fromEl, toEl, true);
            }

            // Stabilise l'état DOM immédiatement après le déplacement
            prev[targetRank] = moving;
            prev[fromRank] = null;
            setTop3FromFilenames(prev);
            refreshSlotVisibility();
        }

        // 3) Insertion de l'image courante au bon rang
        if (currentImageEl && currentImageEl.style.display !== 'none') {
            const targetEl = slotElForRank(insertedRank);
            await flyTo(currentImageEl, targetEl, true);

            // Une fois rangée dans le Top, elle ne doit plus rester/revenir dans la zone de calcul
            if (currentImageEl === currentImg) {
                currentImg.style.display = 'none';
                currentImg.removeAttribute('src');
                currentImg.style.visibility = '';
                placeholder.style.display = 'block';
            }
        }

        prev[insertedRank] = inserted;
        setTop3FromFilenames(prev);
        refreshSlotVisibility();

        const pulseSlot = slotElForRank(insertedRank)?.parentElement;
        if (pulseSlot) {
            pulseSlot.classList.remove('pulse-highlight');
            void pulseSlot.offsetWidth;
            pulseSlot.classList.add('pulse-highlight');
        }
    };

    const appendDetail = (img) => {
        const details = document.createElement('details');
        details.className = 'img-details detail-entering';
        const summary = document.createElement('summary');

        const left = document.createElement('span');
        left.className = 'summary-title';
        left.textContent = `${img.index}. ${img.filename}`;

        const right = document.createElement('span');
        right.className = 'summary-meta';
        const isKept = (img.top3 || []).some((t) => t.filename === img.filename);
        const statusLabel = isKept ? 'Top 3' : 'jetée';
        right.textContent = `similarité: ${(img.similarity || 0).toFixed(2)}% — ${statusLabel}`;

        summary.appendChild(left);
        summary.appendChild(right);
        details.appendChild(summary);

        const wrap = document.createElement('div');
        wrap.className = 'table-wrap';
        const table = document.createElement('table');
        table.className = 'ram-table';

        // En-têtes identiques au CLI : Étape | Δused | Δalloc | Δpic | Cumulé used | Temps | Explication
        const thead = document.createElement('thead');
        thead.innerHTML = '<tr><th>Étape</th><th>Δused</th><th>Δalloc</th><th>Δpic</th><th>Cumulé used</th><th>Temps</th><th>Explication</th></tr>';
        table.appendChild(thead);
        const tbody = document.createElement('tbody');

        // Classe CSS selon le signe de la valeur delta
        const deltaClass = (v) => v > 0 ? 'delta-pos' : (v < 0 ? 'delta-neg' : 'delta-zero');

        let cumulUsed = 0;
        (img.steps || []).forEach((st) => {
            cumulUsed += (st.deltaUsed || 0);
            const tr = document.createElement('tr');

            // Étape (textContent — protection XSS)
            const tdStep = document.createElement('td');
            tdStep.textContent = st.step || '';
            tr.appendChild(tdStep);

            // Δused
            const tdUsed = document.createElement('td');
            tdUsed.className = deltaClass(st.deltaUsed);
            tdUsed.textContent = fmtBytes(st.deltaUsed || 0);
            tr.appendChild(tdUsed);

            // Δalloc
            const tdAlloc = document.createElement('td');
            tdAlloc.className = deltaClass(st.deltaAlloc);
            tdAlloc.textContent = fmtBytes(st.deltaAlloc || 0);
            tr.appendChild(tdAlloc);

            // Δpic
            const tdPeak = document.createElement('td');
            tdPeak.className = deltaClass(st.deltaPeak);
            tdPeak.textContent = fmtBytes(st.deltaPeak || 0);
            tr.appendChild(tdPeak);

            // Cumulé used (somme courante des Δused — identique à la colonne CLI)
            const tdCumul = document.createElement('td');
            tdCumul.className = 'cumul-col ' + deltaClass(cumulUsed);
            tdCumul.textContent = fmtBytes(cumulUsed);
            tr.appendChild(tdCumul);

            // Temps
            const tdTime = document.createElement('td');
            tdTime.textContent = fmtMs(st.elapsedMs);
            tr.appendChild(tdTime);

            // Explication (textContent — protection XSS)
            const tdExplain = document.createElement('td');
            tdExplain.className = 'explain-col';
            tdExplain.textContent = st.explain || '';
            tr.appendChild(tdExplain);

            tbody.appendChild(tr);
        });
        table.appendChild(tbody);

        // Bilan RAM net (identique au CLI : "Bilan RAM : +12 KB (résidu)")
        if ((img.steps || []).length > 0) {
            const tfoot = document.createElement('tfoot');
            const trBilan = document.createElement('tr');
            trBilan.className = 'bilan-row';
            const tdBilan = document.createElement('td');
            tdBilan.setAttribute('colspan', '7');
            tdBilan.className = 'bilan-cell ' + deltaClass(cumulUsed);
            tdBilan.textContent = `Bilan RAM : ${fmtBytes(cumulUsed)}${cumulUsed <= 0 ? ' ✓ pas de fuite' : ' (résidu — objets PHP internes)'}`;
            trBilan.appendChild(tdBilan);
            tfoot.appendChild(trBilan);
            table.appendChild(tfoot);
        }

        wrap.appendChild(table);
        details.appendChild(wrap);
        detailsWrap.appendChild(details);

        // Entrance animation
        requestAnimationFrame(() => {
            details.classList.remove('detail-entering');
        });
    };

    const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

    const runAnimation = async (payload) => {
        const images = payload.images || [];
        const maxAbs = payload.maxAbs || { used: 1, alloc: 1, peak: 1 };
        const total = images.length;

        // --- Init état global ---
        status.textContent = `Run: ${total} image(s)`;
        status.className = 'status running';
        progressOuter.style.display = 'block';
        progressInner.style.width = '0%';
        imgCounter.style.display = 'block';
        imgCounter.textContent = `0 / ${total}`;
        clearTop3();
        refreshSlotVisibility();
        renderTestsBin(images);
        clearRgbDisplay();
        detailsWrap.innerHTML = '';

        let prevTop3 = [];

        for (let i = 0; i < images.length; i++) {
            const img = images[i];

            // --- Progression globale ---
            const pct = ((i / total) * 100).toFixed(1);
            progressInner.style.width = `${pct}%`;
            imgCounter.textContent = `${i + 1} / ${total}`;

            // --- Reset badge ---
            badge.style.display = 'none';
            badge.className = 'badge';
            badge.classList.remove('badge-show');

            // --- Pickup depuis le bac ---
            const thumb = getThumbFor(img.filename);
            const cell = getBinCellFor(img.filename);
            if (thumb) {
                if (cell) cell.classList.add('active');
                await flyTo(thumb, currentBox);
                if (cell) {
                    cell.classList.remove('active');
                    cell.classList.add('in-progress');
                }
            }

            // --- Affichage image courante avec entrée animée ---
            const src = `${BASE}/storage/images/test/${encodeURIComponent(img.filename)}`;
            currentImg.src = src;
            currentImg.style.display = 'block';
            placeholder.style.display = 'none';
            currentImg.classList.remove('img-entering');
            void currentImg.offsetWidth; // force reflow
            currentImg.classList.add('img-entering');
            currentMeta.textContent = `Image ${img.index}/${total} — ${img.filename}`;
            status.textContent = `Récupération: ${img.filename}`;
            setRgbDisplay(img.rgb);

            // --- Activation scan-line ---
            scanLine.classList.add('scanning');

            await sleep(250);

            // --- Boucle des étapes ---
            for (const st of (img.steps || [])) {
                // slide-in: on met la classe (texte caché haut), on change le texte,
                // puis on retire la classe pour que la transition joue vers visible.
                stepName.classList.add('step-entering');
                stepExplain.classList.add('step-entering');
                void stepName.offsetWidth;
                stepName.textContent = st.step;
                stepExplain.textContent = st.explain;
                stepName.classList.remove('step-entering');
                stepExplain.classList.remove('step-entering');

                if (st.step === 'Ranking Top3') {
                    scanLine.classList.remove('scanning');
                    await animateTop3Transition(prevTop3, img.top3, currentImg);
                    prevTop3 = (img.top3 || []).slice(0);
                    scanLine.classList.add('scanning');
                }

                status.textContent = `Calcul: ${st.step}`;
                await sleep(330);
            }

            // --- Fin scan ---
            scanLine.classList.remove('scanning');
            currentImg.classList.remove('img-entering');

            // --- Badge résultat avec animation bounce ---
            const isInTop3 = (img.top3 || []).some((t) => t.filename === img.filename);
            if (isInTop3) {
                badge.className = 'badge kept';
                badge.textContent = 'Gardée (Top 3)';
            } else {
                badge.className = 'badge dropped';
                badge.textContent = 'Jetée / détruite';
            }
            badge.style.display = 'inline-block';
            void badge.offsetWidth;
            badge.classList.add('badge-show');

            if (!isInTop3) {
                await sleep(350);
                await animateDestroy();
            }

            // --- Bin cell -> done (pas remove) ---
            if (cell) {
                cell.classList.remove('in-progress');
                cell.classList.add('done');
            }

            // --- Ajouter l'accordéon détail une fois l'image traitée ---
            appendDetail(img);

            await sleep(450);
        }

        // --- Fin du run ---
        progressInner.style.width = '100%';
        imgCounter.textContent = `${total} / ${total}`;
        status.textContent = 'Terminé.';
        status.className = 'status done';
    };

    btn.addEventListener('click', async () => {
        // Révèle les sections Tri Top3 + Détails, puis scroll fluide vers Tri
        const triSection = document.getElementById('triSection');
        const detailsSection = document.getElementById('detailsSection');
        if (triSection) {
            triSection.style.display = '';
            triSection.querySelectorAll('.reveal').forEach((el) => {
                setTimeout(() => el.classList.add('visible'), 60);
            });
            setTimeout(() => triSection.scrollIntoView({ behavior: 'smooth', block: 'start' }), 100);
        }
        if (detailsSection) {
            detailsSection.style.display = '';
            detailsSection.querySelectorAll('.reveal').forEach((el) => {
                setTimeout(() => el.classList.add('visible'), 60);
            });
        }

        btn.disabled = true;
        status.textContent = 'Chargement…';
        status.className = 'status running';
        progressOuter.style.display = 'none';
        imgCounter.style.display = 'none';
        try {
            const res = await fetch(RUN_URL, { cache: 'no-store' });
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            const payload = await res.json();
            await runAnimation(payload);
        } catch (e) {
            status.textContent = `Erreur: ${e && e.message ? e.message : String(e)}`;
            status.className = 'status error';
        } finally {
            btn.disabled = false;
        }
    });
})();

    // ── Scroll reveal (IntersectionObserver) ──────────────────
    (() => {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach((e) => {
                if (e.isIntersecting) {
                    e.target.classList.add('visible');
                    observer.unobserve(e.target);
                }
            });
        }, { threshold: 0.10 });
        document.querySelectorAll('.reveal').forEach((el) => observer.observe(el));
    })();
</script>
</body>
</html>
        <?php
        return (string) ob_get_clean();
    }
}
