<?php
/**
 * Interface pour le ranking/sélection des résultats.
 *
 * @author  Margot Hourdillé
 * @project RGBMatch — Challenge Firstruner
 * @version 1.0.0
 * @date    2026-03-06
 * @update  2026-03-06
 */

namespace RGBMatch\Interfaces;

use RGBMatch\Metier\CComparisonResult;

interface IComparisonResultRanker
{
    /**
     * @param array<CComparisonResult> $results
     * @return array<CComparisonResult>
     */
    public function sortBySimilarityDesc(array $results): array;

    /**
     * @param array<CComparisonResult> $topResults
     * @return array<CComparisonResult>
     */
    public function pushTopN(array $topResults, CComparisonResult $candidate, int $count = 3): array;
}
