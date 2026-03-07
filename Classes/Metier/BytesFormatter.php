<?php
/**
 * Utilitaire: formatage lisible d'une taille en octets.
 *
 * Objectif: éviter les duplications de formatBytes() dans plusieurs scripts/classes.
 *
 * @author  Margot Hourdillé
 * @project RGBMatch — Challenge Firstruner
 * @version 1.0.0
 * @date    2026-03-06
 * @update  2026-03-06
 */

namespace RGBMatch\Metier;

final class BytesFormatter
{
    private function __construct() {}

    public static function format(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);

        $pow = (int) floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $value = $bytes / pow(1024, $pow);
        return round($value, 2) . ' ' . $units[$pow];
    }

    /**
     * Parse la valeur de memory_limit (ou toute notation shorthand PHP) en octets.
     *
     * @return int|null null si illimité (-1)
     */
    public static function parseMemoryLimit(): ?int
    {
        $raw = trim((string) ini_get('memory_limit'));
        if ($raw === '' || $raw === '-1') {
            return null;
        }
        if (ctype_digit($raw)) {
            return (int) $raw;
        }
        $units = ['g' => 1024 ** 3, 'm' => 1024 ** 2, 'k' => 1024];
        $unit  = strtolower(substr($raw, -1));
        $num   = (int) substr($raw, 0, -1);
        return ($num > 0 && isset($units[$unit])) ? $num * $units[$unit] : null;
    }
}
