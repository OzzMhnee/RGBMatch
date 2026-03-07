<?php
/**
 * Interface pour les analyseurs d'images instrumentés (avec métriques RAM/temps).
 *
 * Étend IImageAnalyzer en ajoutant analyzeWithStats() pour les cas éducatifs
 * où l'on veut visualiser l'impact mémoire de chaque étape GD.
 *
 * Convention: Prefix I pour Interface
 * Principe: ISP (Interface Segregation) — seul le code éducatif dépend de cette interface élargie.
 *
 * @author  Margot Hourdillé
 * @project RGBMatch — Challenge Firstruner
 * @version 1.0.0
 * @date    2026-03-06
 * @update  2026-03-06
 */

namespace RGBMatch\Interfaces;

use RGBMatch\Metier\CRgbPercentage;

interface IInstrumentedImageAnalyzer extends IImageAnalyzer
{
    /**
     * Analyse une image et retourne les données RGB + métriques détaillées.
     * La ressource GD est libérée à l'intérieur de cette méthode.
     *
     * @param string $imagePath Chemin vers l'image
     * @return array{rgb:CRgbPercentage, stats:array{loadSeconds:float, downscaleSeconds:float, calcSeconds:float, freeSeconds:float, didDownscale:bool, originalW:int, originalH:int, workW:int, workH:int, snaps:array<string, array{used:int,alloc:int,peakAlloc:int}>}}
     */
    public function analyzeWithStats(string $imagePath): array;

    /**
     * Variante qui retourne la ressource GD SANS la libérer.
     * Le CALLER doit appeler freeGd() sur la ressource retournée.
     * Utile pour contrôler l'instant exact de libération (tests pédagogiques, permutations…).
     *
     * @param string $imagePath Chemin vers l'image
     * @return array{rgb:CRgbPercentage, stats:array{loadSeconds:float, downscaleSeconds:float, calcSeconds:float, didDownscale:bool, originalW:int, originalH:int, workW:int, workH:int, snaps:array<string, array{used:int,alloc:int,peakAlloc:int}>}, gd:mixed}
     */
    public function analyzeWithStatsKeepGd(string $imagePath): array;

    /**
     * Libère une ressource GD retournée par analyzeWithStatsKeepGd().
     *
     * @param \GdImage|resource|null $gd Ressource à libérer
     */
    public function freeGd($gd): void;
}
