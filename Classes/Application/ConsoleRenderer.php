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
        return <<<'ASCII'
      ______   ________  ________   __       __  __    __  __    __  ________  ________ 
     /      \ /        |/        | |  \     /  \|  \  |  \|  \  |  \|        \|        \
    /$$$$$$  |$$$$$$$$/ $$$$$$$$/  | $$\   /  $$| $$  | $$| $$\ | $$| $$$$$$$$| $$$$$$$$
    $$ |  $$ |    /$$/      /$$/   | $$$\ /  $$$| $$__| $$| $$$\| $$| $$__    | $$__    
    $$ |  $$ |   /$$/      /$$/    | $$$$\  $$$$| $$    $$| $$$$\ $$| $$  \   | $$  \   
    $$ |  $$ |  /$$/      /$$/     | $$\$$ $$ $$| $$$$$$$$| $$\$$ $$| $$$$$   | $$$$$   
    $$ \__$$ | /$$/____  /$$/____  | $$ \$$$| $$| $$  | $$| $$ \$$$$| $$_____ | $$_____ 
    $$    $$/ /$$      |/$$     /  | $$  \$ | $$| $$  | $$| $$  \$$$| $$     \| $$     \
    $$$$$$/  $$$$$$$$/ $$$$$$$$/    \$$      \$$ \$$   \$$ \$$   \$$ \$$$$$$$$ \$$$$$$$$
ASCII;
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
        if (preg_match_all('/./u', $value, $m) !== false) {
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
        if (preg_match_all('/./u', $value, $m) !== false) {
            $chars = $m[0];
            $take = max(0, $width - 1);
            return implode('', array_slice($chars, 0, $take)) . '…';
        }

        return substr($value, 0, $width - 1) . '…';
    }

    /**
     * Coupe une chaîne à une largeur d'affichage exacte, sans suffixe.
     */
    private static function sliceByWidth(string $value, int $width): string
    {
        if ($width <= 0 || $value === '') {
            return '';
        }

        if (self::strWidth($value) <= $width) {
            return $value;
        }

        if (function_exists('mb_strimwidth')) {
            return (string) mb_strimwidth($value, 0, $width, '', 'UTF-8');
        }

        if (preg_match_all('/./u', $value, $m) !== false) {
            $chars = $m[0];
            $buffer = '';
            foreach ($chars as $char) {
                if (self::strWidth($buffer . $char) > $width) {
                    break;
                }
                $buffer .= $char;
            }

            return $buffer;
        }

        return substr($value, 0, $width);
    }

    /**
     * Version multioctet de str_pad basée sur la largeur d'affichage réelle.
     */
    private static function mbStrPad(
        string $text,
        int $padLength,
        string $padString = ' ',
        int $padType = STR_PAD_RIGHT
    ): string {
        $textWidth = self::strWidth($text);
        $padNeeded = $padLength - $textWidth;
        if ($padNeeded <= 0) {
            return $text;
        }

        if ($padString === '') {
            $padString = ' ';
        }

        $buildPad = static function (int $targetWidth) use ($padString): string {
            if ($targetWidth <= 0) {
                return '';
            }

            $pad = '';
            while (ConsoleRenderer::strWidth($pad) < $targetWidth) {
                $pad .= $padString;
            }

            return ConsoleRenderer::sliceByWidth($pad, $targetWidth);
        };

        switch ($padType) {
            case STR_PAD_LEFT:
                return $buildPad($padNeeded) . $text;

            case STR_PAD_BOTH:
                $left = intdiv($padNeeded, 2);
                $right = $padNeeded - $left;
                return $buildPad($left) . $text . $buildPad($right);

            case STR_PAD_RIGHT:
            default:
                return $text . $buildPad($padNeeded);
        }
    }

    /**
     * Pad à droite (alignement gauche) en fonction de la largeur d'affichage.
     */
    private static function padRight(string $value, int $width): string
    {
        return self::mbStrPad($value, $width, ' ', STR_PAD_RIGHT);
    }

    private static function padCenter(string $value, int $width): string
    {
        return self::mbStrPad($value, $width, ' ', STR_PAD_BOTH);
    }

    private static function renderBorderLine(string $indent, array $widths, string $left, string $mid, string $right): string
    {
        $parts = [];
        foreach ($widths as $width) {
            $parts[] = str_repeat('─', $width + 2);
        }

        return $indent . $left . implode($mid, $parts) . $right . "\n";
    }

    private static function renderBoxLine(string $indent, int $innerWidth, string $text = '', string $alignment = 'left'): string
    {
        $content = $alignment === 'center'
            ? self::padCenter($text, $innerWidth)
            : self::padRight($text, $innerWidth);

        return $indent . '│ ' . $content . ' │' . "\n";
    }

    /**
     * @param array<int, string> $cells
     * @param array<int, string>|null $alignments
     */
    private static function renderRow(string $indent, array $cells, array $widths, string $alignment = 'left', ?array $alignments = null): string
    {
        $line = $indent . '│';
        foreach ($cells as $i => $cell) {
            $value = (string) $cell;
            $cellAlignment = (string) ($alignments[$i] ?? $alignment);

            if ($cellAlignment === 'center') {
                $value = self::padCenter($value, $widths[$i]);
            } else {
                $value = self::padRight($value, $widths[$i]);
            }

            $line .= ' ' . $value . ' │';
        }

        return $line . "\n";
    }

    /**
     * Découpe une cellule en plusieurs lignes sans casser l'alignement UTF-8.
     *
     * @return array<int, string>
     */
    private static function wrapCell(string $value, int $width): array
    {
        if ($width <= 0) {
            return [''];
        }

        $value = trim($value);
        if ($value === '') {
            return [''];
        }

        $paragraphs = preg_split('/\R/u', $value) ?: [$value];
        $lines = [];

        foreach ($paragraphs as $paragraph) {
            $paragraph = trim((string) $paragraph);
            if ($paragraph === '') {
                $lines[] = '';
                continue;
            }

            $tokens = preg_split('/\s+/u', $paragraph) ?: [$paragraph];
            $currentLine = '';

            foreach ($tokens as $token) {
                $token = (string) $token;
                if ($token === '') {
                    continue;
                }

                if (self::strWidth($token) > $width) {
                    if ($currentLine !== '') {
                        $lines[] = $currentLine;
                        $currentLine = '';
                    }

                    $remaining = $token;
                    while ($remaining !== '') {
                        $chunk = self::sliceByWidth($remaining, $width);
                        if ($chunk === '') {
                            break;
                        }

                        $lines[] = $chunk;
                        $remaining = trim((string) preg_replace('/^' . preg_quote($chunk, '/') . '/u', '', $remaining, 1));
                    }

                    continue;
                }

                $candidate = $currentLine === '' ? $token : $currentLine . ' ' . $token;
                if (self::strWidth($candidate) <= $width) {
                    $currentLine = $candidate;
                    continue;
                }

                $lines[] = $currentLine;
                $currentLine = $token;
            }

            if ($currentLine !== '') {
                $lines[] = $currentLine;
            }
        }

        return $lines === [] ? [''] : $lines;
    }

    /**
     * @param array<int, string> $headers
     * @param array<int, array<int, string>> $rows
    * @param array{indent?:string,minWidths?:array<int,int>,maxWidths?:array<int,int>,headerAlign?:string,columnAlign?:array<int,string>} $options
     */
    public function printTable(array $headers, array $rows, array $options = []): void
    {
        $columnCount = count($headers);
        if ($columnCount === 0) {
            return;
        }

        $indent = (string) ($options['indent'] ?? '');
        $minWidths = (array) ($options['minWidths'] ?? []);
        $maxWidths = (array) ($options['maxWidths'] ?? []);
        $headerAlign = (string) ($options['headerAlign'] ?? 'center');
        $columnAlign = (array) ($options['columnAlign'] ?? []);
        $widths = [];

        for ($i = 0; $i < $columnCount; $i++) {
            $header = (string) ($headers[$i] ?? '');
            $width = max((int) ($minWidths[$i] ?? 1), self::strWidth($header));
            $maxWidth = isset($maxWidths[$i]) ? (int) $maxWidths[$i] : 0;
            if ($maxWidth > 0) {
                $width = min($width, $maxWidth);
            }
            $widths[$i] = $width;
        }

        foreach ($rows as $row) {
            for ($i = 0; $i < $columnCount; $i++) {
                $cell = (string) ($row[$i] ?? '');
                $cellWidth = self::strWidth($cell);
                $maxWidth = isset($maxWidths[$i]) ? (int) $maxWidths[$i] : 0;
                if ($maxWidth > 0) {
                    $cellWidth = min($cellWidth, $maxWidth);
                }
                $widths[$i] = max($widths[$i], $cellWidth, (int) ($minWidths[$i] ?? 1));
            }
        }

        echo self::renderBorderLine($indent, $widths, '┌', '┬', '┐');

        $headerCells = [];
        foreach ($headers as $i => $header) {
            $headerCells[$i] = self::fit((string) $header, $widths[$i]);
        }
        echo self::renderRow($indent, $headerCells, $widths, $headerAlign);
        echo self::renderBorderLine($indent, $widths, '├', '┼', '┤');

        foreach ($rows as $row) {
            $wrappedCells = [];
            $rowHeight = 1;

            for ($i = 0; $i < $columnCount; $i++) {
                $wrappedCells[$i] = self::wrapCell((string) ($row[$i] ?? ''), $widths[$i]);
                $rowHeight = max($rowHeight, count($wrappedCells[$i]));
            }

            for ($lineIndex = 0; $lineIndex < $rowHeight; $lineIndex++) {
                $cells = [];
                for ($i = 0; $i < $columnCount; $i++) {
                    $cells[$i] = (string) ($wrappedCells[$i][$lineIndex] ?? '');
                }
                echo self::renderRow($indent, $cells, $widths, 'left', $columnAlign);
            }
        }

        echo self::renderBorderLine($indent, $widths, '└', '┴', '┘');
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
        echo "╔" . str_repeat('═', 60) . "╗\n";
        echo '║ ' . self::padCenter($title, 58) . " ║\n";
        echo "╚" . str_repeat('═', 60) . "╝\n";
    }

    /**
     * @param array<int, string> $lines
     * @param array{indent?:string,width?:int,title?:string,titleAlign?:string} $options
     */
    public function printTextBox(array $lines, array $options = []): void
    {
        $indent = (string) ($options['indent'] ?? '');
        $requestedWidth = (int) ($options['width'] ?? 0);
        $title = trim((string) ($options['title'] ?? ''));
        $titleAlign = (string) ($options['titleAlign'] ?? 'left');

        $innerWidth = max(1, $requestedWidth);
        // En mode auto-largeur (aucun requestedWidth), on grandit jusqu'à la ligne la plus large.
        // En mode largeur fixée, le contenu débordant est renvoyé à la ligne via wrapCell.
        if ($requestedWidth <= 0) {
            foreach ($lines as $line) {
                $innerWidth = max($innerWidth, self::strWidth((string) $line));
            }
        }
        if ($title !== '') {
            $innerWidth = max($innerWidth, self::strWidth($title) + 2);
        }

        if ($title !== '') {
            $titleText = self::fit($title, max(1, $innerWidth));
            $available = max(0, $innerWidth + 2 - self::strWidth($titleText) - 2);
            if ($titleAlign === 'center') {
                $left = intdiv($available, 2);
                $right = $available - $left;
            } else {
                $left = 1;
                $right = max(0, $available - 1);
            }

            echo $indent . '┌' . str_repeat('─', $left) . ' ' . $titleText . ' ' . str_repeat('─', $right) . '┐' . "\n";
        } else {
            echo $indent . '┌' . str_repeat('─', $innerWidth + 2) . '┐' . "\n";
        }

        foreach ($lines as $line) {
            foreach (self::wrapCell((string) $line, $innerWidth) as $wrappedLine) {
                echo self::renderBoxLine($indent, $innerWidth, $wrappedLine);
            }
        }

        echo $indent . '└' . str_repeat('─', $innerWidth + 2) . '┘' . "\n";
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
        $tableRows = [];
        foreach ($rows as $row) {
            $tableRows[] = [
                (string) ($row['step'] ?? ''),
                (string) ($row['time'] ?? ''),
                (string) ($row['ram'] ?? ''),
                (string) ($row['note'] ?? ''),
                (string) ($row['explain'] ?? ''),
            ];
        }

        $this->printTable($headers, $tableRows, [
            'minWidths' => [11, 6, 11, 16, 18],
            'maxWidths' => [20, 10, 14, 26, 44],
            'columnAlign' => ['left', 'center', 'center', 'left', 'left'],
        ]);

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

        $this->printTable($headers, $rows, [
            'minWidths' => [14, 8, 8, 8, 11, 6, 18],
            'maxWidths' => [22, 12, 12, 12, 14, 10, 64],
            'columnAlign' => ['left', 'center', 'center', 'center', 'center', 'center', 'left'],
        ]);
    }
}
