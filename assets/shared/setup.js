/**
 * Logique du formulaire de setup (initialisation des images Unsplash).
 * Utilisé sur la page Setup et la page Analyse.
 *
 * © 2026 Tous droits réservés.
 * @project RGBMatch — Challenge Firstruner
 */
(function () {
    'use strict';

    var bodyDataset = document.body ? document.body.dataset : {};
    var SETUP_URL = bodyDataset.setupUrl || '';

    var btnSetup    = document.getElementById('btnSetup');
    var setupStatus = document.getElementById('setupStatus');
    var setupCount  = document.getElementById('setupCount');
    var setupQuery  = document.getElementById('setupQuery');
    var requestTimeoutMs = 120000;

    if (!btnSetup || !SETUP_URL) {
        return;
    }

    var setSetupState = function (kind, msg) {
        if (!setupStatus) {
            return;
        }
        setupStatus.className = 'status' + (kind ? ' ' + kind : '');
        setupStatus.textContent = msg;
    };

    var normalizeQuery = function (value) {
        return String(value || '')
            .replace(/[’]/g, "'")
            .replace(/[‐‑‒–—]/g, '-')
            .replace(/\s+/g, ' ')
            .trim();
    };

    var validateQuery = function (value) {
        if (value === '') {
            return null;
        }

        if (value.length > 60) {
            return 'Le theme est trop long (60 caracteres maximum).';
        }

        if (/[&;<>={}\[\]\\/]/.test(value)) {
            return 'Theme invalide. Supprimez les caracteres speciaux reserves.';
        }

        var alnumMatches = value.match(/[\p{L}\p{N}]/gu) || [];
        if (alnumMatches.length < 2) {
            return 'Theme invalide. Utilisez au moins 2 caracteres alphanumeriques.';
        }

        var wordMatches = value.match(/[\p{L}\p{N}]+/gu) || [];
        if (wordMatches.length > 5) {
            return 'Theme invalide. Utilisez au maximum 5 mots.';
        }

        if (!/^[\p{L}\p{N}]+(?:[ '-][\p{L}\p{N}]+)*$/u.test(value)) {
            return 'Theme invalide. Utilisez uniquement des lettres, chiffres, espaces, apostrophes ou tirets.';
        }

        return null;
    };

    btnSetup.addEventListener('click', async function () {
        var count = parseInt((setupCount && setupCount.value) || '10', 10);
        var query = normalizeQuery((setupQuery && setupQuery.value) || '');

        if (setupQuery) {
            setupQuery.value = query;
        }

        if (!Number.isFinite(count) || count < 6 || count > 30) {
            setSetupState('error', "Nombre d'images invalide (min 6, max 30).");
            return;
        }

        var queryError = validateQuery(query);
        if (queryError) {
            setSetupState('error', queryError);
            return;
        }

        btnSetup.disabled = true;

        // Désactiver aussi btnStart s'il existe (page analyse)
        var btnStart = document.getElementById('btnStart');
        if (btnStart) {
            btnStart.disabled = true;
        }

        setSetupState('running', 'Téléchargement en cours… (Unsplash)');

        if (typeof navigator !== 'undefined' && navigator.onLine === false) {
            setSetupState('error', 'Connexion indisponible. Rebranchez le reseau puis relancez le setup.');
            btnSetup.disabled = false;
            if (btnStart) {
                btnStart.disabled = false;
            }
            return;
        }

        var abortController = typeof AbortController !== 'undefined' ? new AbortController() : null;
        var timeoutId = setTimeout(function () {
            if (abortController) {
                abortController.abort();
            }
        }, requestTimeoutMs);

        try {
            var response = await fetch(SETUP_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                signal: abortController ? abortController.signal : undefined,
                body: JSON.stringify({ count: count, query: query }),
            });

            clearTimeout(timeoutId);

            var rawText = await response.text();
            var data = null;
            try {
                data = rawText ? JSON.parse(rawText) : null;
            } catch (parseError) {
                data = null;
            }

            if (!response.ok) {
                var message = data && data.message ? data.message : 'Erreur HTTP ' + response.status;
                throw new Error(message);
            }

            if (!data || data.ok !== true || !data.result) {
                throw new Error('Réponse de setup invalide : le endpoint JSON attendu n\'a pas répondu correctement.');
            }

            var seconds = data && data.result ? data.result.seconds : null;
            var downloaded = data && data.result ? data.result.downloadedTests : 0;
            var formattedSeconds = Number.isFinite(seconds) ? seconds.toFixed(2) + 's' : '';
            setSetupState('done', 'Setup terminé. Tests téléchargés: ' + downloaded + '. ' + formattedSeconds);
            setTimeout(function () {
                window.location.reload();
            }, 800);
        } catch (error) {
            clearTimeout(timeoutId);
            var errMsg = error && error.message ? error.message : String(error);
            if (error && error.name === 'AbortError') {
                errMsg = 'Timeout reseau pendant le setup. Verifiez la connexion puis recommencez.';
            }
            setSetupState('error', 'Échec du setup : ' + errMsg);
            btnSetup.disabled = false;
            if (btnStart) {
                btnStart.disabled = false;
            }
        }
    });
})();
