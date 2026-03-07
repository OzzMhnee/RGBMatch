<?php
/**
 * Builder pour construire un objet CImageData depuis un chemin d'image
 * Pattern: Builder
 * Principe: SRP - Responsable uniquement de la construction de CImageData
 *
 * @author  Margot Hourdillé
 * @project RGBMatch — Challenge Firstruner
 * @version 1.0.0
 * @date    2026-03-06
 * @update  2026-03-06
 */

namespace RGBMatch\Builders;

use InvalidArgumentException;
use RGBMatch\Enumerations\EImageType;
use RGBMatch\Interfaces\IImageAnalyzer;
use RGBMatch\Interfaces\IImageDataBuilder;
use RGBMatch\Metier\CImageData;

final class ImageDataBuilder

    implements IImageDataBuilder
{
    /** @var IImageAnalyzer */
    private $analyzer;
    
    public function __construct(IImageAnalyzer $analyzer)
    {
        $this->analyzer = $analyzer;
    }
    
    /**
     * Construit un CImageData depuis un chemin d'image
     * 
     * @param string $imagePath Chemin vers l'image
     * @return CImageData Objet métier construit
     * @throws InvalidArgumentException Si l'image n'existe pas ou type non supporté
     */
    public function build(string $imagePath): CImageData
    {
        // Vérification de l'existence
        if (!file_exists($imagePath)) {
            throw new InvalidArgumentException("L'image n'existe pas : {$imagePath}");
        }
        
        // Détection du type
        $type = EImageType::fromPath($imagePath);
        if ($type === null) {
            throw new InvalidArgumentException("Type d'image non supporté : {$imagePath}");
        }
        
        // Analyse RGB
        $rgbPercentage = $this->analyzer->analyze($imagePath);
        
        // Construction de l'objet métier
        return new CImageData($imagePath, $rgbPercentage, $type);
    }
    
}
