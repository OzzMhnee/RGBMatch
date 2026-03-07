<?php
/**
 * Builder pour construire des résultats de comparaison
 * Pattern: Builder
 * Principe: SRP - Responsable uniquement de la construction de CComparisonResult
 *
 * @author  Margot Hourdillé
 * @project RGBMatch — Challenge Firstruner
 * @version 1.0.0
 * @date    2026-03-06
 * @update  2026-03-06
 */

namespace RGBMatch\Builders;

use RGBMatch\Interfaces\IComparisonResultBuilder;
use RGBMatch\Metier\CComparisonResult;
use RGBMatch\Metier\CImageData;

final class ComparisonResultBuilder

    implements IComparisonResultBuilder
{
    /**
     * Construit un résultat de comparaison entre deux images
     * 
     * @param CImageData $origin Image d'origine
     * @param CImageData $test Image de test
     * @return CComparisonResult Résultat de la comparaison
     */
    public function build(CImageData $origin, CImageData $test): CComparisonResult
    {
        $originRgb = $origin->getRgbPercentage();
        $testRgb = $test->getRgbPercentage();
        
        // Calcul de la similarité
        $similarity = $originRgb->calculateSimilarity($testRgb);
        
        // Calcul des différences
        $differences = $originRgb->calculateDifference($testRgb);
        $differences['total'] = $differences['r'] + $differences['g'] + $differences['b'];
        
        return new CComparisonResult($test->getPath(), $testRgb, $similarity, $differences);
    }
    
}
