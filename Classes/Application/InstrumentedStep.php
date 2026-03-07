<?php
/**
 * DRY : encapsule le pattern récurrent « snap before → action → snap after → delta ».
 *
 * Avant l'extraction, ce pattern (8 lignes) était copié ~15 fois dans index.php et public/index.php.
 * Désormais, un seul appel suffit :
 *
 *   $step = InstrumentedStep::run('Build CImageData', 'Objet léger', function () use ($path, $rgb, $type) {
 *       return new CImageData($path, $rgb, $type);
 *   });
 *   // $step contient : label, explain, deltaUsed, deltaAlloc, deltaPeak, elapsedMs, returnValue
 *
 * @author  Margot Hourdillé
 * @project RGBMatch — Challenge Firstruner
 * @version 1.0.0
 * @date    2026-03-06
 * @update  2026-03-06
 */

namespace RGBMatch\Application;

final class InstrumentedStep
{
    /**
     * Exécute un callable en mesurant RAM (used/alloc/peak) et temps.
     *
     * @param string   $label   Nom de l'étape (ex. "Build CImageData")
     * @param string   $explain Explication courte (pédagogique)
     * @param callable $action  Code à mesurer
     * @return array{step:string, explain:string, deltaUsed:int, deltaAlloc:int, deltaPeak:int, elapsedMs:int, returnValue:mixed}
     */
    public static function run(string $label, string $explain, callable $action): array
    {
        $beforeUsed  = memory_get_usage(false);
        $beforeAlloc = memory_get_usage(true);
        $beforePeak  = memory_get_peak_usage(true);
        $t0 = microtime(true);

        $returnValue = $action();

        $elapsedMs   = (int) round((microtime(true) - $t0) * 1000);
        $afterUsed   = memory_get_usage(false);
        $afterAlloc  = memory_get_usage(true);
        $afterPeak   = memory_get_peak_usage(true);

        return [
            'step'        => $label,
            'explain'     => $explain,
            'deltaUsed'   => $afterUsed  - $beforeUsed,
            'deltaAlloc'  => $afterAlloc - $beforeAlloc,
            'deltaPeak'   => $afterPeak  - $beforePeak,
            'elapsedMs'   => $elapsedMs,
            'returnValue' => $returnValue,
        ];
    }

    /**
     * Variante sans retour de valeur (ex. unset + GC).
     *
     * @return array{step:string, explain:string, deltaUsed:int, deltaAlloc:int, deltaPeak:int, elapsedMs:int}
     */
    public static function measure(string $label, string $explain, callable $action): array
    {
        $result = self::run($label, $explain, $action);
        unset($result['returnValue']);
        return $result;
    }
}
