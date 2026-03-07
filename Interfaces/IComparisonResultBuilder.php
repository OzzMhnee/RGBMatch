<?php
/**
 * Interface pour les builders de résultats de comparaison.
 *
 * @author  Margot Hourdillé
 * @project RGBMatch — Challenge Firstruner
 * @version 1.0.0
 * @date    2026-03-06
 * @update  2026-03-06
 */

namespace RGBMatch\Interfaces;

use RGBMatch\Metier\CComparisonResult;
use RGBMatch\Metier\CImageData;

interface IComparisonResultBuilder
{
    public function build(CImageData $origin, CImageData $test): CComparisonResult;
}
