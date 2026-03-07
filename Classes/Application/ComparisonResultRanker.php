<?php
/**
 * Classe dédiée au ranking/sélection des résultats de comparaison.
 * Principe: SRP - Responsable uniquement du tri et de la sélection (Top N).
 *
 * @author  Margot Hourdillé
 * @project RGBMatch — Challenge Firstruner
 * @version 1.0.0
 * @date    2026-03-06
 * @update  2026-03-06
 */

namespace RGBMatch\Application;

use RGBMatch\Interfaces\IComparisonResultRanker;
use RGBMatch\Metier\CComparisonResult;

final class ComparisonResultRanker

    implements IComparisonResultRanker
{
    /**
     * Trie les résultats par similarité décroissante.
     *
     * @param array<CComparisonResult> $results
     * @return array<CComparisonResult>
     */
    public function sortBySimilarityDesc(array $results): array
    {
        usort($results, static fn($a, $b) => $b->getSimilarity() <=> $a->getSimilarity());
        return $results;
    }

    /**
     * Insère un candidat dans une liste Top-N (triée par similarité décroissante).
     * Permet de sélectionner les meilleurs résultats sans conserver toute la liste.
     *
     * @param array<CComparisonResult> $topResults Liste triée (desc) de taille <= $count
     * @param CComparisonResult $candidate
     * @param int $count
     * @return array<CComparisonResult>
     */
    public function pushTopN(array $topResults, CComparisonResult $candidate, int $count = 3): array
    {
        if ($count <= 0) {
            return [];
        }

        // Si on n'a pas encore $count éléments, on insère puis on trie.
        if (count($topResults) < $count) {
            $topResults[] = $candidate;
            return $this->sortBySimilarityDesc($topResults);
        }

        // Liste déjà pleine: si le candidat est <= au dernier, on jette.
        $last = $topResults[count($topResults) - 1];
        if ($candidate->getSimilarity() <= $last->getSimilarity()) {
            return $topResults;
        }

        // Sinon on remplace le dernier et on retrie (N petit: coût négligeable).
        $topResults[count($topResults) - 1] = $candidate;
        $topResults = $this->sortBySimilarityDesc($topResults);
        return array_slice($topResults, 0, $count);
    }
}
