<?php
/**
 * Rendu console : bannières, sections, tableaux et formatage CLI.
 *
 * @author  Margot Hourdillé
 * @project RGBMatch — Challenge Firstruner
 * @version 1.0.0
 * @date    2026-03-06
 * @update  2026-03-06
 */

namespace RGBMatch\Application;

use RGBMatch\Metier\BytesFormatter;

/**
 * Renderer console (CLI) : SRP = uniquement le rendu/formatage.
 */
final class ConsoleRenderer
{
    public static function signatureArt(): string
    {
        // Signature ASCII simple (évite les dépendances, reste lisible en monospace ~80 colonnes).
        return implode("\n", [
            '   OOOOO  zzzzzzz  zzzzzzz  M   M  H   H  N   N  EEEEE  EEEEE',
            '   O   O      zz       zz   MM MM  H   H  NN  N  E      E',
            '   O   O     zz       zz    M M M  HHHHH  N N N  EEEE   EEEE',
            '   O   O    zz       zz     M   M  H   H  N  NN  E      E',
            '   OOOOO  zzzzzzz  zzzzzzz  M   M  H   H  N   N  EEEEE  EEEEE',
        ]) . "\n";
    }

    /**
     * Largeur d'affichage (monospace) d'une chaîne UTF-8.
     * Fallback en mode byte si mbstring n'est pas disponible.
     */
    private static function strWidth(string $value): int
    {
        if (function_exists('mb_strwidth')) {
            return (int) mb_strwidth($value, 'UTF-8');
        }

        // Fallback UTF-8 sans mbstring : compte les caractères Unicode.
        // Suffisant ici (accents, symboles, flèches) pour conserver l'alignement.
        if (preg_match_all('/./u', $value, $m) === 1) {
            return count($m[0]);
        }

        return strlen($value);
    }

    /**
     * Tronque une chaîne à une largeur d'affichage maximale.
     * Ajoute une ellipse si besoin.
     */
    private static function fit(string $value, int $width): string
    {
        $value = trim($value);
        if ($width <= 0) {
            return '';
        }

        if (self::strWidth($value) <= $width) {
            return $value;
        }

        if ($width === 1) {
            return '…';
        }

        if (function_exists('mb_strimwidth')) {
            return (string) mb_strimwidth($value, 0, $width, '…', 'UTF-8');
        }

        // Fallback UTF-8 : tronque en caractères si possible.
        if (preg_match_all('/./u', $value, $m) === 1) {
            $chars = $m[0];
            $take = max(0, $width - 1);
            return implode('', array_slice($chars, 0, $take)) . '…';
        }

        return substr($value, 0, $width - 1) . '…';
    }

    /**
     * Pad à droite (alignement gauche) en fonction de la largeur d'affichage.
     */
    private static function padRight(string $value, int $width): string
    {
        $pad = max(0, $width - self::strWidth($value));
        return $value . str_repeat(' ', $pad);
    }

    public function banner(): void
    {
        echo "\n";
        echo "╔══════════════════════════════════════════════════════╗\n";
        echo "║                                                      ║\n";
        echo "║         SYSTÈME DE COMPARAISON RGB D'IMAGES          ║\n";
        echo "║                                                      ║\n";
        echo "╚══════════════════════════════════════════════════════╝\n\n";

    }

    public function section(string $title): void
    {
        $maxWidth = 58;
        if (self::strWidth($title) > $maxWidth) {
            $title = self::fit($title, $maxWidth);
        }

        echo "\n";
        echo "╔" . str_repeat('═', 58) . "╗\n";
        echo '║ ' . self::padRight($title, 58) . " ║\n";
        echo "╚" . str_repeat('═', 58) . "╝\n";
    }

    public function formatDuration(float $seconds): string
    {
        if ($seconds < 1) {
            return sprintf('%.0f ms', $seconds * 1000);
        }

        return sprintf('%.3f s', $seconds);
    }

    public function traceTime(string $label, float $seconds): void
    {
        echo sprintf("[TIME] %-40s %s\n", $label, $this->formatDuration($seconds));
    }

    public function awaitMs(int $milliseconds, string $label = ''): void
    {
        $milliseconds = max(0, $milliseconds);
        if ($milliseconds === 0) {
            return;
        }

        if ($label !== '') {
            echo sprintf("[WAIT] %s (%d ms)\n", $label, $milliseconds);
        }

        usleep($milliseconds * 1000);
    }

    public function withDots(string $label, int $dots = 3, int $ms = 350): void
    {
        $dots = max(0, $dots);
        $ms = max(0, $ms);

        echo $label;
        for ($i = 0; $i < $dots; $i++) {
            usleep($ms * 1000);
            echo '.';
            self::doFlush();
        }
        echo "\n";
    }

    /**
     * Pause interactive : affiche un prompt et attend ENTRÉE.
     * Ne fait rien si le script n'est pas en mode CLI, si STDIN n'est pas
     * un terminal interactif, ou si l'argument --batch est présent.
     */
    public function pressEnter(string $prompt = '  ⏎  Appuyez sur ENTRÉE pour continuer...'): void
    {
        if (PHP_SAPI !== 'cli' && PHP_SAPI !== 'phpdbg') {
            return;
        }
        // --batch = mode non-interactif (CI, tests, pipes…)
        if (in_array('--batch', $_SERVER['argv'] ?? [], true)) {
            echo "\n";
            return;
        }
        if (!defined('STDIN')) {
            echo "\n";
            return;
        }
        // stream_isatty (PHP 7.2+) fonctionne sur Windows ET Linux
        if (function_exists('stream_isatty') && !stream_isatty(STDIN)) {
            echo "\n";
            return;
        }
        echo "\n" . $prompt;
        self::doFlush();
        fgets(STDIN);
    }

    /**
     * Force le vidage des buffers de sortie.
     */
    private static function doFlush(): void
    {
        if (function_exists('flush')) {
            @flush();
        }
        if (defined('STDOUT')) {
            @fflush(STDOUT);
        }
    }

    /**
     * @param array<int, array{step:string,time:string,ram:string,note:string,explain?:string}> $rows
     */
    public function printMiniTable(string $contextLabel, string $contextValue, array $rows, string $afterNote = ''): void
    {
        echo sprintf("%s: %s\n", $contextLabel, $contextValue);

        $headers = ['Sous-étape', 'Temps', 'ΔRAM (used)', 'Fonction', 'Ce que ça fait'];
        $keys    = ['step', 'time', 'ram', 'note', 'explain'];
        $minW    = [11, 6, 11, 16, 18];

        // Auto-dimensionnement depuis les en-têtes et les données.
        $w = $minW;
        foreach ($headers as $i => $h) {
            $w[$i] = max($w[$i], self::strWidth($h));
        }
        foreach ($rows as $r) {
            foreach ($keys as $i => $k) {
                $w[$i] = max($w[$i], self::strWidth((string) ($r[$k] ?? '')));
            }
        }

        $makeLine = static function (string $l, string $m, string $r) use ($w): string {
            $parts = [];
            foreach ($w as $cw) {
                $parts[] = str_repeat('─', $cw + 2);
            }
            return $l . implode($m, $parts) . $r . "\n";
        };

        echo $makeLine('┌', '┬', '┐');
        echo '│';
        foreach ($headers as $i => $h) {
            echo ' ' . self::padRight(self::fit($h, $w[$i]), $w[$i]) . ' │';
        }
        echo "\n";
        echo $makeLine('├', '┼', '┤');

        foreach ($rows as $r) {
            echo '│';
            foreach ($keys as $i => $k) {
                $cell = self::fit((string) ($r[$k] ?? ''), $w[$i]);
                echo ' ' . self::padRight($cell, $w[$i]) . ' │';
            }
            echo "\n";
        }

        echo $makeLine('└', '┴', '┘');
        if ($afterNote !== '') {
            echo $afterNote . "\n";
        }
    }

    /**
     * @param array<int, object> $results objets ayant une méthode toArray()
     */
    public function displayResults(array $results): void
    {
        echo "\n Top 3 des images les plus similaires :\n\n";

        foreach ($results as $index => $result) {
            if (is_object($result) && method_exists($result, 'toArray')) {
                /** @var array $data */
                $data = $result->toArray();
            } elseif (is_array($result)) {
                /** @var array $data */
                $data = $result;
            } else {
                continue;
            }
            echo sprintf("#{%d} - %s\n", $index + 1, (string) ($data['filename'] ?? '')); 
            echo sprintf("   Similarité: %.2f%%\n", (float) ($data['similarity'] ?? 0));
            echo sprintf(
                "   RGB: R=%.2f%% G=%.2f%% B=%.2f%%\n",
                (float) ($data['rgb']['r'] ?? 0),
                (float) ($data['rgb']['g'] ?? 0),
                (float) ($data['rgb']['b'] ?? 0)
            );
            echo sprintf(
                "   Différences: R=%.2f G=%.2f B=%.2f (Total: %.2f)\n",
                (float) ($data['diff']['r'] ?? 0),
                (float) ($data['diff']['g'] ?? 0),
                (float) ($data['diff']['b'] ?? 0),
                (float) ($data['diff']['total'] ?? 0)
            );
            echo "\n";
        }
    }

    public function formatBytes(int $bytes): string
    {
        return BytesFormatter::format($bytes);
    }

    /**
     * Affiche un tableau RAM détaillé (étapes de l'orchestrateur).
     *
     * Colonnes : Étape | Δused | Δalloc | Δpic | Cumulé used | Temps | Explication
     * Utilise des box-drawing chars et des largeurs auto-calculées pour un alignement parfait.
     *
     * @param array $steps Tableau de steps (deltaUsed, deltaAlloc, deltaPeak, elapsedMs, explain)
     */
    public function printRamTable(array $steps): void
    {
        $headers = ['Étape', 'Δused', 'Δalloc', 'Δpic', 'Cumulé used', 'Temps', 'Explication'];
        $maxExplain = 64;

        $fmtSigned = static function (int $b): string {
            if ($b === 0) {
                return '0 B';
            }
            return ($b < 0 ? '-' : '+') . BytesFormatter::format(abs($b));
        };

        // Construire toutes les lignes de données d'abord.
        $rows = [];
        $cumulUsed = 0;
        foreach ($steps as $st) {
            $cumulUsed += (int) ($st['deltaUsed'] ?? 0);
            $rows[] = [
                (string) ($st['step'] ?? ''),
                $fmtSigned((int) ($st['deltaUsed'] ?? 0)),
                $fmtSigned((int) ($st['deltaAlloc'] ?? 0)),
                $fmtSigned((int) ($st['deltaPeak'] ?? 0)),
                $fmtSigned($cumulUsed),
                $this->formatDuration(((int) ($st['elapsedMs'] ?? 0)) / 1000.0),
                (string) ($st['explain'] ?? ''),
            ];
        }

        // Largeurs minimales par colonne.
        $w = [14, 8, 8, 8, 11, 6, $maxExplain];

        // Auto-dimensionnement depuis les en-têtes et les données.
        foreach ($headers as $i => $h) {
            $w[$i] = max($w[$i], self::strWidth($h));
        }
        foreach ($rows as $r) {
            foreach ($r as $i => $cell) {
                $cellW = self::strWidth($cell);
                if ($i === 6) {
                    $cellW = min($cellW, $maxExplain);
                }
                $w[$i] = max($w[$i], $cellW);
            }
        }

        // Génère une ligne de séparation avec des box-drawing chars.
        $makeLine = static function (string $l, string $m, string $r) use ($w): string {
            $parts = [];
            foreach ($w as $cw) {
                $parts[] = str_repeat('─', $cw + 2);
            }
            return $l . implode($m, $parts) . $r . "\n";
        };

        echo $makeLine('┌', '┬', '┐');
        echo '│';
        foreach ($headers as $i => $h) {
            echo ' ' . self::padRight(self::fit($h, $w[$i]), $w[$i]) . ' │';
        }
        echo "\n";
        echo $makeLine('├', '┼', '┤');

        foreach ($rows as $r) {
            echo '│';
            foreach ($r as $i => $cell) {
                echo ' ' . self::padRight(self::fit($cell, $w[$i]), $w[$i]) . ' │';
            }
            echo "\n";
        }
        echo $makeLine('└', '┴', '┘');
    }
}
