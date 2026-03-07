<?php
/**
 * Classe Métier: Données d'une image analysée
 * Convention: Prefix C pour Classe Métier
 * @package MatchRGB\Classes\Metier
 *
 * @author  Margot Hourdillé
 * @project RGBMatch — Challenge Firstruner
 * @version 1.0.0
 * @date    2026-03-06
 * @update  2026-03-06
 */

namespace RGBMatch\Metier;

use RGBMatch\Enumerations\EImageType;

final class CImageData
{
    private $path;
    private $rgbPercentage;
    private $type;
    
    public function __construct(string $path, CRgbPercentage $rgbPercentage, EImageType $type)
    {
        $this->path = $path;
        $this->rgbPercentage = $rgbPercentage;
        $this->type = $type;
    }
    
    public function getPath(): string
    {
        return $this->path;
    }
    
    public function getFilename(): string
    {
        return basename($this->path);
    }
    
    public function getRgbPercentage(): CRgbPercentage
    {
        return $this->rgbPercentage;
    }
    
    public function getType(): EImageType
    {
        return $this->type;
    }
    
    /**
     * Vérifie si l'image existe toujours
     */
    public function exists(): bool
    {
        return file_exists($this->path);
    }
}
