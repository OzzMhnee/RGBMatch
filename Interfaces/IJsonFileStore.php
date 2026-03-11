<?php
/**
 * Contrat de lecture/ecriture de fichiers JSON structures.
 *
 * @author  Margot Hourdillé
 * @project RGBMatch — Challenge Firstruner
 * @version 1.0.0
 * @date    2026-03-11
 */

namespace RGBMatch\Interfaces;

interface IJsonFileStore
{
    /**
     * @return array<string,mixed>|null
     */
    public function read(string $path): ?array;

    /**
     * @param array<string,mixed> $payload
     */
    public function write(string $path, array $payload): void;
}