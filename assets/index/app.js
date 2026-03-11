/**
 * Orchestrateur principal de la page d'analyse RGBMatch.
 *
 * Dépend de :
 *   - shared/utils.js   (RGBMatch.fmtBytes, fmtMs, sleep, escapeCssValue)
 *   - index/animation.js (RGBMatch.animation.flyTo, fadeOutEl)
 *   - shared/setup.js   (gestion autonome du formulaire setup)
 *
 * © 2026 Tous droits réservés.
 * @project RGBMatch — Challenge Firstruner
 */
(function () {
    'use strict';

    var ns = window.RGBMatch || {};
    var fmtBytes = ns.fmtBytes;
    var fmtMs = ns.fmtMs;
    var sleep = ns.sleep;
    var escapeCssValue = ns.escapeCssValue;
    var flyTo = ns.animation ? ns.animation.flyTo : null;
    var fadeOutEl = ns.animation ? ns.animation.fadeOutEl : null;

    var bodyDataset = document.body ? document.body.dataset : {};
    var RUN_URL = bodyDataset.runUrl || '';
    var BASE = bodyDataset.baseUrl || '';

    var btn = document.getElementById('btnStart');
    var status = document.getElementById('status');
    var progressOuter = document.getElementById('progressOuter');
    var progressInner = document.getElementById('progressInner');
    var currentBox = document.getElementById('currentBox');
    var currentImg = document.getElementById('currentImg');
    var placeholder = document.getElementById('currentPlaceholder');
    var badge = document.getElementById('badge');
    var scanLine = document.getElementById('scanLine');
    var imgCounter = document.getElementById('imgCounter');
    var currentMeta = document.getElementById('currentMeta');
    var detailsWrap = document.getElementById('detailsWrap');

    var stepName = document.getElementById('stepName');
    var stepExplain = document.getElementById('stepExplain');

    var valR = document.getElementById('valR');
    var valG = document.getElementById('valG');
    var valB = document.getElementById('valB');
    var barR = document.getElementById('barR');
    var barG = document.getElementById('barG');
    var barB = document.getElementById('barB');
    var testsBin = document.getElementById('testsBin');
    var topImg1 = document.getElementById('topImg1');
    var topImg2 = document.getElementById('topImg2');
    var topImg3 = document.getElementById('topImg3');

    if (!btn || !status || !progressOuter || !progressInner || !imgCounter || !testsBin || !topImg1 || !topImg2 || !topImg3) {
        return;
    }

    // ── RGB display ─────────────────────────────────────────

    var setRgbDisplay = function (rgb) {
        if (!rgb) {
            return;
        }
        var r = Number(rgb.r) || 0;
        var g = Number(rgb.g) || 0;
        var b = Number(rgb.b) || 0;
        valR.textContent = r.toFixed(2) + '%';
        valG.textContent = g.toFixed(2) + '%';
        valB.textContent = b.toFixed(2) + '%';
        barR.style.width = Math.min(100, r).toFixed(2) + '%';
        barG.style.width = Math.min(100, g).toFixed(2) + '%';
        barB.style.width = Math.min(100, b).toFixed(2) + '%';
    };

    var clearRgbDisplay = function () {
        [valR, valG, valB].forEach(function (el) { el.textContent = '—'; });
        [barR, barG, barB].forEach(function (el) { el.style.width = '0%'; });
    };

    // ── Top 3 management ────────────────────────────────────

    var clearTop3 = function () {
        topImg1.removeAttribute('src');
        topImg2.removeAttribute('src');
        topImg3.removeAttribute('src');
    };

    var setTop3FromArray = function (arr) {
        var items = arr || [];
        var setOrClear = function (imgEl, item) {
            if (item && item.filename) {
                imgEl.src = BASE + '/storage/images/test/' + encodeURIComponent(item.filename);
            } else {
                imgEl.removeAttribute('src');
            }
        };
        setOrClear(topImg1, items[0]);
        setOrClear(topImg2, items[1]);
        setOrClear(topImg3, items[2]);
    };

    var top3Filenames = function (arr) {
        var items = arr || [];
        return [
            items[0] && items[0].filename ? items[0].filename : null,
            items[1] && items[1].filename ? items[1].filename : null,
            items[2] && items[2].filename ? items[2].filename : null,
        ];
    };

    var setTop3FromFilenames = function (files) {
        var mapped = (files || []).slice(0, 3).map(function (f) {
            return f ? { filename: f } : null;
        });
        while (mapped.length < 3) { mapped.push(null); }
        setTop3FromArray(mapped);
    };

    var withSrcClass = function (imgEl) {
        if (imgEl.getAttribute('src')) {
            imgEl.classList.add('has-src');
        } else {
            imgEl.classList.remove('has-src');
        }
    };

    var refreshSlotVisibility = function () {
        [topImg1, topImg2, topImg3].forEach(function (el) {
            withSrcClass(el);
            var slot = el.closest('.top3-slot');
            if (!slot) { return; }
            if (el.getAttribute('src')) {
                slot.classList.remove('slot-empty');
                slot.classList.add('slot-filled');
            } else {
                slot.classList.add('slot-empty');
                slot.classList.remove('slot-filled');
            }
        });
    };

    var slotElForRank = function (idx) {
        return idx === 0 ? topImg1 : (idx === 1 ? topImg2 : topImg3);
    };

    // ── Tests bin ───────────────────────────────────────────

    var renderTestsBin = function (images) {
        testsBin.innerHTML = '';
        (images || []).forEach(function (img) {
            var cell = document.createElement('div');
            cell.className = 'bin-item';
            cell.dataset.filename = img.filename;
            var hiddenImg = document.createElement('img');
            hiddenImg.className = 'bin-thumb';
            hiddenImg.alt = img.filename;
            hiddenImg.dataset.filename = img.filename;
            hiddenImg.src = BASE + '/storage/images/test/' + encodeURIComponent(img.filename);
            cell.appendChild(hiddenImg);
            testsBin.appendChild(cell);
        });
    };

    var getThumbFor = function (filename) {
        return testsBin.querySelector('img.bin-thumb[data-filename="' + escapeCssValue(filename) + '"]');
    };

    var getBinCellFor = function (filename) {
        return testsBin.querySelector('.bin-item[data-filename="' + escapeCssValue(filename) + '"]');
    };

    // ── Animations ──────────────────────────────────────────

    var animateDestroy = async function () {
        scanLine.classList.remove('scanning');
        currentImg.classList.add('drop-animation');
        await sleep(600);
        currentImg.classList.remove('drop-animation');
        currentImg.style.display = 'none';
        currentImg.removeAttribute('src');
        currentImg.style.visibility = '';
        placeholder.style.display = 'block';
    };

    var animateTop3Transition = async function (prevTop3, nextTop3, currentImageEl) {
        var prev = top3Filenames(prevTop3);
        var next = top3Filenames(nextTop3);

        var inserted = next.find(function (f) { return f && !prev.includes(f); });
        if (!inserted) {
            setTop3FromFilenames(next);
            refreshSlotVisibility();
            return;
        }

        var insertedRank = next.indexOf(inserted);
        var leaving = prev.find(function (f) { return f && !next.includes(f); });

        if (leaving) {
            var leavingRank = prev.indexOf(leaving);
            var leavingEl = slotElForRank(leavingRank);
            await fadeOutEl(leavingEl, 320);
            prev[leavingRank] = null;
            setTop3FromFilenames(prev);
            refreshSlotVisibility();
        }

        for (var targetRank = 2; targetRank > insertedRank; targetRank--) {
            var shouldBeThere = next[targetRank];
            var fromRank = targetRank - 1;
            var moving = prev[fromRank];
            if (!shouldBeThere || !moving || moving !== shouldBeThere) { continue; }
            var fromEl = slotElForRank(fromRank);
            var toEl = slotElForRank(targetRank);
            if (fromEl.getAttribute('src')) { await flyTo(fromEl, toEl, true); }
            prev[targetRank] = moving;
            prev[fromRank] = null;
            setTop3FromFilenames(prev);
            refreshSlotVisibility();
        }

        if (currentImageEl && currentImageEl.style.display !== 'none') {
            var targetEl = slotElForRank(insertedRank);
            await flyTo(currentImageEl, targetEl, true);
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

        var slotEl = slotElForRank(insertedRank);
        var pulseSlot = slotEl && slotEl.parentElement ? slotEl.parentElement : null;
        if (pulseSlot) {
            pulseSlot.classList.remove('pulse-highlight');
            void pulseSlot.offsetWidth;
            pulseSlot.classList.add('pulse-highlight');
        }
    };

    // ── Details panel ───────────────────────────────────────

    var appendDetail = function (img) {
        var details = document.createElement('details');
        details.className = 'img-details detail-entering';
        var summary = document.createElement('summary');

        var left = document.createElement('span');
        left.className = 'summary-title';
        left.textContent = img.index + '. ' + img.filename;

        var right = document.createElement('span');
        right.className = 'summary-meta';
        var isKept = (img.top3 || []).some(function (item) { return item.filename === img.filename; });
        right.textContent = 'similarité: ' + (img.similarity || 0).toFixed(2) + '% — ' + (isKept ? 'Top 3' : 'jetée');

        summary.appendChild(left);
        summary.appendChild(right);
        details.appendChild(summary);

        var wrap = document.createElement('div');
        wrap.className = 'table-wrap';
        var table = document.createElement('table');
        table.className = 'ram-table';

        var thead = document.createElement('thead');
        thead.innerHTML = '<tr><th>Étape</th><th>Δused</th><th>Δalloc</th><th>Δpic</th><th>Cumulé used</th><th>Temps</th><th>Explication</th></tr>';
        table.appendChild(thead);
        var tbody = document.createElement('tbody');

        var deltaClass = function (v) { return v > 0 ? 'delta-pos' : (v < 0 ? 'delta-neg' : 'delta-zero'); };

        var cumulUsed = 0;
        (img.steps || []).forEach(function (step) {
            cumulUsed += (step.deltaUsed || 0);
            var row = document.createElement('tr');

            var tdStep = document.createElement('td');
            tdStep.textContent = step.step || '';
            row.appendChild(tdStep);

            var tdUsed = document.createElement('td');
            tdUsed.className = deltaClass(step.deltaUsed);
            tdUsed.textContent = fmtBytes(step.deltaUsed || 0);
            row.appendChild(tdUsed);

            var tdAlloc = document.createElement('td');
            tdAlloc.className = deltaClass(step.deltaAlloc);
            tdAlloc.textContent = fmtBytes(step.deltaAlloc || 0);
            row.appendChild(tdAlloc);

            var tdPeak = document.createElement('td');
            tdPeak.className = deltaClass(step.deltaPeak);
            tdPeak.textContent = fmtBytes(step.deltaPeak || 0);
            row.appendChild(tdPeak);

            var tdCumul = document.createElement('td');
            tdCumul.className = 'cumul-col ' + deltaClass(cumulUsed);
            tdCumul.textContent = fmtBytes(cumulUsed);
            row.appendChild(tdCumul);

            var tdTime = document.createElement('td');
            tdTime.textContent = fmtMs(step.elapsedMs);
            row.appendChild(tdTime);

            var tdExplain = document.createElement('td');
            tdExplain.className = 'explain-col';
            tdExplain.textContent = step.explain || '';
            row.appendChild(tdExplain);

            tbody.appendChild(row);
        });
        table.appendChild(tbody);

        if ((img.steps || []).length > 0) {
            var tfoot = document.createElement('tfoot');
            var footRow = document.createElement('tr');
            footRow.className = 'bilan-row';
            var cell = document.createElement('td');
            cell.setAttribute('colspan', '7');
            cell.className = 'bilan-cell ' + deltaClass(cumulUsed);
            cell.textContent = 'Bilan RAM : ' + fmtBytes(cumulUsed) + (cumulUsed <= 0 ? ' ✓ pas de fuite' : ' (résidu — objets PHP internes)');
            footRow.appendChild(cell);
            tfoot.appendChild(footRow);
            table.appendChild(tfoot);
        }

        wrap.appendChild(table);
        details.appendChild(wrap);
        detailsWrap.appendChild(details);

        requestAnimationFrame(function () { details.classList.remove('detail-entering'); });
    };

    // ── Main run animation ──────────────────────────────────

    var runAnimation = async function (payload) {
        var images = payload.images || [];
        var total = images.length;

        status.textContent = 'Run: ' + total + ' image(s)';
        status.className = 'status running';
        progressOuter.style.display = 'block';
        progressInner.style.width = '0%';
        imgCounter.style.display = 'block';
        imgCounter.textContent = '0 / ' + total;
        clearTop3();
        refreshSlotVisibility();
        renderTestsBin(images);
        clearRgbDisplay();
        detailsWrap.innerHTML = '';

        var prevTop3 = [];

        for (var i = 0; i < images.length; i++) {
            var img = images[i];
            var pct = ((i / total) * 100).toFixed(1);
            progressInner.style.width = pct + '%';
            imgCounter.textContent = (i + 1) + ' / ' + total;

            badge.style.display = 'none';
            badge.className = 'badge';
            badge.classList.remove('badge-show');

            var thumb = getThumbFor(img.filename);
            var cell = getBinCellFor(img.filename);
            if (thumb) {
                if (cell) { cell.classList.add('active'); }
                await flyTo(thumb, currentBox, false);
                if (cell) { cell.classList.remove('active'); cell.classList.add('in-progress'); }
            }

            var src = BASE + '/storage/images/test/' + encodeURIComponent(img.filename);
            currentImg.src = src;
            currentImg.style.display = 'block';
            placeholder.style.display = 'none';
            currentImg.classList.remove('img-entering');
            void currentImg.offsetWidth;
            currentImg.classList.add('img-entering');
            currentMeta.textContent = 'Image ' + img.index + '/' + total + ' — ' + img.filename;
            status.textContent = 'Récupération: ' + img.filename;
            setRgbDisplay(img.rgb);
            scanLine.classList.add('scanning');

            await sleep(250);

            for (var s = 0; s < (img.steps || []).length; s++) {
                var step = img.steps[s];
                stepName.classList.add('step-entering');
                stepExplain.classList.add('step-entering');
                void stepName.offsetWidth;
                stepName.textContent = step.step;
                stepExplain.textContent = step.explain;
                stepName.classList.remove('step-entering');
                stepExplain.classList.remove('step-entering');

                if (step.step === 'Ranking Top3') {
                    scanLine.classList.remove('scanning');
                    await animateTop3Transition(prevTop3, img.top3, currentImg);
                    prevTop3 = (img.top3 || []).slice(0);
                    scanLine.classList.add('scanning');
                }

                status.textContent = 'Calcul: ' + step.step;
                await sleep(330);
            }

            scanLine.classList.remove('scanning');
            currentImg.classList.remove('img-entering');

            var isInTop3 = (img.top3 || []).some(function (item) { return item.filename === img.filename; });
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

            if (cell) {
                cell.classList.remove('in-progress');
                cell.classList.add('done');
            }

            appendDetail(img);
            await sleep(450);
        }

        progressInner.style.width = '100%';
        imgCounter.textContent = total + ' / ' + total;
        status.textContent = 'Terminé.';
        status.className = 'status done';
    };

    // ── Button listener ─────────────────────────────────────

    btn.addEventListener('click', async function () {
        var triSection = document.getElementById('triSection');
        var detailsSection = document.getElementById('detailsSection');

        if (triSection) {
            triSection.style.display = '';
            triSection.querySelectorAll('.reveal').forEach(function (el) {
                setTimeout(function () { el.classList.add('visible'); }, 60);
            });
            setTimeout(function () {
                triSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }, 100);
        }

        if (detailsSection) {
            detailsSection.style.display = '';
            detailsSection.querySelectorAll('.reveal').forEach(function (el) {
                setTimeout(function () { el.classList.add('visible'); }, 60);
            });
        }

        btn.disabled = true;
        status.textContent = 'Chargement…';
        status.className = 'status running';
        progressOuter.style.display = 'none';
        imgCounter.style.display = 'none';

        try {
            var response = await fetch(RUN_URL, { cache: 'no-store' });
            if (!response.ok) {
                throw new Error('HTTP ' + response.status);
            }
            var payload = await response.json();
            await runAnimation(payload);
        } catch (error) {
            status.textContent = 'Erreur: ' + (error && error.message ? error.message : String(error));
            status.className = 'status error';
        } finally {
            btn.disabled = false;
        }
    });
})();
