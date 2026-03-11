/**
 * Animations visuelles pour l'analyse RGBMatch (fly-to, destroy, etc.).
 *
 * Expose : window.RGBMatch.animation
 *
 * © 2026 Tous droits réservés.
 * @project RGBMatch — Challenge Firstruner
 */
window.RGBMatch = window.RGBMatch || {};

(function (ns) {
    'use strict';

    var sleep = ns.sleep;

    var frameElFor = function (element) {
        return (element && element.closest && element.closest('.top3-slot'))
            || (element && element.closest && element.closest('.current-image'))
            || (element && element.closest && element.closest('.bin-item'))
            || element;
    };

    var applyCloneFrameStyleFromTarget = function (clone, targetEl) {
        var frame = frameElFor(targetEl);
        if (!frame) {
            return;
        }

        var computedStyle = getComputedStyle(frame);
        clone.style.borderRadius = '0';
        if (computedStyle.border && computedStyle.border !== '0px none rgb(0, 0, 0)') {
            clone.style.border = computedStyle.border;
        }
        if (computedStyle.boxShadow && computedStyle.boxShadow !== 'none') {
            clone.style.boxShadow = computedStyle.boxShadow;
        }
        clone.style.background = 'transparent';
    };

    var createFlyClone = function (imgEl, fromRect, toRect) {
        var clone = imgEl.cloneNode(true);
        var fromCx = fromRect.left + fromRect.width / 2;
        var fromCy = fromRect.top + fromRect.height / 2;

        clone.style.position = 'fixed';
        clone.style.width = toRect.width + 'px';
        clone.style.height = toRect.height + 'px';
        clone.style.left = (fromCx - toRect.width / 2) + 'px';
        clone.style.top = (fromCy - toRect.height / 2) + 'px';
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

    ns.animation = {
        flyTo: async function (fromImgEl, toImgEl, hideFrom) {
            var fromRect = fromImgEl.getBoundingClientRect();
            var toRect = toImgEl.getBoundingClientRect();
            var clone = createFlyClone(fromImgEl, fromRect, toRect);
            var previousVisibility = fromImgEl.style.visibility;

            try {
                applyCloneFrameStyleFromTarget(clone, toImgEl);
                if (hideFrom) {
                    fromImgEl.style.visibility = 'hidden';
                }

                var startLeft = parseFloat(clone.style.left) || 0;
                var startTop = parseFloat(clone.style.top) || 0;
                var dx = toRect.left - startLeft;
                var dy = toRect.top - startTop;

                clone.getBoundingClientRect();
                clone.style.transform = 'translate(' + dx + 'px, ' + dy + 'px)';
                await sleep(620);
            } finally {
                clone.remove();
                if (hideFrom) {
                    fromImgEl.style.visibility = previousVisibility;
                }
            }
        },

        fadeOutEl: async function (element, ms) {
            element.classList.add('fade-out');
            await sleep(ms || 360);
            element.classList.remove('fade-out');
        }
    };
})(window.RGBMatch);
