/**
 * Utilitaires partagés pour les scripts RGBMatch.
 *
 * © 2026 Tous droits réservés.
 * @project RGBMatch — Challenge Firstruner
 */
window.RGBMatch = window.RGBMatch || {};

(function (ns) {
    'use strict';

    ns.fmtBytes = function (n) {
        if (n === 0) {
            return '0 B';
        }

        var abs = Math.abs(n);
        var sign = n < 0 ? '-' : '+';
        var units = ['B', 'KB', 'MB', 'GB'];
        var value = abs;
        var unitIndex = 0;

        while (value >= 1024 && unitIndex < units.length - 1) {
            value /= 1024;
            unitIndex++;
        }

        var formatted = unitIndex === 0 ? String(Math.round(value)) : String(parseFloat(value.toFixed(2)));
        return sign + formatted + ' ' + units[unitIndex];
    };

    ns.fmtMs = function (n) {
        var ms = Number.isFinite(n) ? Math.max(0, n) : 0;
        if (ms >= 1000) {
            return (ms / 1000).toFixed(3) + ' s';
        }
        return String(Math.round(ms)) + ' ms';
    };

    ns.sleep = function (ms) {
        return new Promise(function (resolve) {
            setTimeout(resolve, ms);
        });
    };

    ns.escapeCssValue = function (value) {
        if (window.CSS && typeof window.CSS.escape === 'function') {
            return window.CSS.escape(value);
        }
        return String(value).replace(/(["\\])/g, '\\$1');
    };
})(window.RGBMatch);
