<?php
/**
 * Interface pour les builders de CImageData.
 * Objectif: permettre l'injection (DIP) et faciliter les tests/mocks.
 *
 * @author  Margot Hourdillé
 * @project RGBMatch — Challenge Firstruner
 * @version 1.0.0
 * @date    2026-03-06
 * @update  2026-03-06
 */

namespace RGBMatch\Interfaces;

use RGBMatch\Metier\CImageData;

interface IImageDataBuilder
{
    /**
    * @throws \InvalidArgumentException
    * @throws \RuntimeException
     */
    public function build(string $imagePath): CImageData;
}
