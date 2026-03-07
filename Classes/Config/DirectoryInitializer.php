<?php
/**
 * Initialisation et création des répertoires nécessaires.
 *
 * @author  Margot Hourdillé
 * @project RGBMatch — Challenge Firstruner
 * @version 1.0.0
 * @date    2026-03-06
 * @update  2026-03-06
 */

namespace RGBMatch\Config;

/**
 * SRP: garantir que les dossiers nécessaires existent.
 */
final class DirectoryInitializer
{
    /**
     * @param string[] $directories
     * @return array{created: string[], existing: string[], failed: string[]}
     */
    public function ensure(array $directories, int $mode = 0775): array
    {
        $created = [];
        $existing = [];
        $failed = [];

        foreach ($directories as $dir) {
            if (!is_string($dir) || trim($dir) === '') {
                continue;
            }

            $dir = rtrim($dir, "\\/\t\n\r\0\x0B");
            if ($dir === '') {
                continue;
            }

            if (is_dir($dir)) {
                $existing[] = $dir;
                continue;
            }

            $ok = @mkdir($dir, $mode, true);
            if ($ok && is_dir($dir)) {
                $created[] = $dir;
            } else {
                $failed[] = $dir;
            }
        }

        return ['created' => $created, 'existing' => $existing, 'failed' => $failed];
    }
}
