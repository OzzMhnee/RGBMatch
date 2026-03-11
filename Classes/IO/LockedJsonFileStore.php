<?php
/**
 * Façade JSON verrouillée au-dessus de LockedFileSession.
 *
 * Centralise la lecture/écriture de fichiers JSON structurés avec verrouillage
 * pour éviter la duplication de la sérialisation et des accès disque.
 *
 * @author  Margot Hourdillé
 * @project RGBMatch — Challenge Firstruner
 * @version 1.0.0
 * @date    2026-03-11
 */

namespace RGBMatch\IO;

use RuntimeException;
use RGBMatch\Interfaces\IJsonFileStore;

final class LockedJsonFileStore implements IJsonFileStore
{
    /**
     * @return array<string,mixed>|null
     */
    public function read(string $path): ?array
    {
        if (!is_file($path)) {
            return null;
        }

        $session = new LockedFileSession();

        try {
            $session->openSharedRead($path);
            $content = $session->process(static function ($handle) {
                $json = stream_get_contents($handle);
                return is_string($json) ? $json : '';
            });
        } finally {
            $session->close();
        }

        if (!is_string($content) || $content === '') {
            return null;
        }

        $decoded = json_decode($content, true);
        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param array<string,mixed> $payload
     */
    public function write(string $path, array $payload): void
    {
        $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        if (!is_string($encoded)) {
            throw new RuntimeException('Impossible de sérialiser le fichier JSON.');
        }

        $session = new LockedFileSession();

        try {
            $session->openExclusiveWrite($path, 'cb');
            $session->process(static function ($handle) use ($encoded): void {
                ftruncate($handle, 0);
                fwrite($handle, $encoded);
                fflush($handle);
            });
        } finally {
            $session->close();
        }
    }
}