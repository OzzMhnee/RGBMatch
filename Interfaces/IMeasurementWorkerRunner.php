<?php
/**
 * Contrat d'exécution du worker de mesures isolé.
 *
 * @author  Margot Hourdillé
 * @project RGBMatch — Challenge Firstruner
 * @version 1.0.0
 * @date    2026-03-09
 * @update  2026-03-09
 */

namespace RGBMatch\Interfaces;

interface IMeasurementWorkerRunner
{
    /**
     * Lance le worker isolé et retourne son payload décodé.
     *
     * @param array{sampleRate:int,maxDimension:int,permCount:int} $options
     * @return array<string,mixed>
     */
    public function run(string $projectRoot, array $options): array;
}