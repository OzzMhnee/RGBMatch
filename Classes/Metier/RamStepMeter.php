<?php
/**
 * Mesure minimale d'une sous-étape (durée + delta RAM used).
 *
 * Objectif pédagogique: limiter la "pollution" des deltas par l'instrumentation
 * (pas de snapshots en array, pas de closure, pas de formatage pendant la mesure).
 *
 * Note: on mesure uniquement memory_get_usage(false) ("used").
 *
 * @author  Margot Hourdillé
 * @project RGBMatch — Challenge Firstruner
 * @version 1.0.0
 * @date    2026-03-06
 * @update  2026-03-06
 */

namespace RGBMatch\Metier;

final class RamStepMeter
{
    /** @var float */
    private $startTime = 0.0;

    /** @var int */
    private $startUsed = 0;

    public function start(): void
    {
        $this->startTime = microtime(true);
        $this->startUsed = memory_get_usage(false);
    }

    /**
     * Stoppe la mesure et renvoie Δused (end - start).
     *
     * @param float|null $seconds OUT Durée en secondes
     * @param int|null $startUsed OUT used au début
     * @param int|null $endUsed OUT used à la fin
     */
    public function stop(?float &$seconds = null, ?int &$startUsed = null, ?int &$endUsed = null): int
    {
        $end = memory_get_usage(false);
        $seconds = microtime(true) - $this->startTime;

        $startUsed = $this->startUsed;
        $endUsed = $end;

        return $end - $this->startUsed;
    }
}
