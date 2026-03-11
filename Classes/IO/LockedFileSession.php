<?php
/**
 * Session de fichier verrouillée et réutilisable.
 *
 * Encapsule le cycle open -> lock -> process -> unlock -> close pour éviter
 * la duplication et garantir une libération cohérente des handles.
 *
 * @author  Margot Hourdillé
 * @project RGBMatch — Challenge Firstruner
 * @version 1.0.0
 * @date    2026-03-11
 */

namespace RGBMatch\IO;

use LogicException;
use RuntimeException;

final class LockedFileSession
{
    /** @var resource|null */
    private $handle = null;

    /** @var string */
    private $path = '';

    public function openSharedRead(string $path): void
    {
        $this->open($path, 'rb', LOCK_SH);
    }

    public function openExclusiveWrite(string $path, string $mode = 'cb'): void
    {
        $this->open($path, $mode, LOCK_EX);
    }

    /**
     * @param callable $handler
     * @return mixed
     */
    public function process(callable $handler)
    {
        if ($this->handle === null) {
            throw new LogicException('Fichier non ouvert');
        }

        return $handler($this->handle);
    }

    public function close(): void
    {
        if ($this->handle === null) {
            return;
        }

        flock($this->handle, LOCK_UN);
        fclose($this->handle);

        $this->handle = null;
        $this->path = '';
    }

    public function __destruct()
    {
        $this->close();
    }

    private function open(string $path, string $mode, int $lockType): void
    {
        if ($this->handle !== null) {
            throw new LogicException('Une session fichier est deja ouverte');
        }

        $handle = @fopen($path, $mode);
        if ($handle === false) {
            throw new RuntimeException("Impossible d'ouvrir le fichier : {$path}");
        }

        if (!flock($handle, $lockType)) {
            fclose($handle);
            throw new RuntimeException("Impossible de verrouiller le fichier : {$path}");
        }

        $this->handle = $handle;
        $this->path = $path;
    }
}