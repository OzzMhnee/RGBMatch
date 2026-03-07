<?php
/**
 * Interface pour les analyseurs d'images
 * Convention: Prefix I pour Interface
 * Principe: OCP (Open/Closed) - Ouvert à l'extension, fermé à la modification
 *
 * @author  Margot Hourdillé
 * @project RGBMatch — Challenge Firstruner
 * @version 1.0.0
 * @date    2026-03-06
 * @update  2026-03-06
 */

namespace RGBMatch\Interfaces;

use RGBMatch\Metier\CRgbPercentage;

interface IImageAnalyzer
{
    /**
     * Analyse une image et retourne les données RGB
     * 
     * @param string $imagePath Chemin vers l'image
     * @return CRgbPercentage Données RGB encapsulées
     * @throws InvalidArgumentException Si l'image n'existe pas
     * @throws RuntimeException Si l'image ne peut être chargée
     */
    public function analyze(string $imagePath): CRgbPercentage;
}
