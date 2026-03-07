<?php
/**
 * Factory de services (composition root).
 *
 * Élimine la duplication de construction des dépendances
 * entre la CLI (index.php), le web (results.php) et l'endpoint JSON (public/index.php).
 *
 * Code mort supprimé : ImageComparator n'est plus instancié (classe supprimée).
 *
 * @author  Margot Hourdillé
 * @project RGBMatch — Challenge Firstruner
 * @version 1.0.0
 * @date    2026-03-06
 * @update  2026-03-06
 */

namespace RGBMatch\Application;

use RGBMatch\Api\GdImageLoader;
use RGBMatch\Api\RgbImageAnalyzer;
use RGBMatch\Builders\ComparisonResultBuilder;
use RGBMatch\Builders\ImageDataBuilder;
use RGBMatch\Interfaces\IImageAnalyzer;
use RGBMatch\Interfaces\IComparisonResultBuilder;
use RGBMatch\Interfaces\IComparisonResultRanker;
use RGBMatch\Interfaces\IImageDataBuilder;

final class ServiceFactory
{
    private function __construct() {}

    /**
     * Crée tous les services nécessaires à l'analyse/comparaison.
     *
     * @param array{sampleRate?:int, maxDimension?:int} $analyzerOptions
     * @return array{0:IImageDataBuilder, 1:IComparisonResultBuilder, 2:IComparisonResultRanker}
     */
    public static function createImageServices(array $analyzerOptions = []): array
    {
        $imageLoader = new GdImageLoader();
        $analyzer = new RgbImageAnalyzer($imageLoader, $analyzerOptions);
        return self::createImageServicesFromAnalyzer($analyzer);
    }

    /**
     * Variante : construit les services à partir d'un analyzer déjà instancié.
     *
     * @return array{0:IImageDataBuilder, 1:IComparisonResultBuilder, 2:IComparisonResultRanker}
     */
    public static function createImageServicesFromAnalyzer(IImageAnalyzer $analyzer): array
    {
        $imageDataBuilder = new ImageDataBuilder($analyzer);
        $comparisonResultBuilder = new ComparisonResultBuilder();
        $ranker = new ComparisonResultRanker();

        return [$imageDataBuilder, $comparisonResultBuilder, $ranker];
    }
}
