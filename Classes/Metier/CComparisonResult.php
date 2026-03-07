<?php
/**
 * Classe métier : résultat d'une comparaison d'images.
 *
 * @author  Margot Hourdillé
 * @project RGBMatch — Challenge Firstruner
 * @version 1.0.0
 * @date    2026-03-06
 * @update  2026-03-06
 */

namespace RGBMatch\Metier;

use InvalidArgumentException;

/**
 * Classe métier représentant le résultat d'une comparaison d'images.
 *
 * Encapsule :
 * - L'image de test (CImageData)
 * - Le score de similarité (float, 0 à 100)
 * - Les différences RGB (array)
 *
 * @package MatchRGB\Classes\Metier
 */
final class CComparisonResult
{

    /**
     * @var string Chemin de l'image de test
     */
    private $path;

    /**
     * @var CRgbPercentage Pourcentages RGB de l'image de test
     */
    private $rgbPercentage;

    /**
     * @var float Score de similarité (0 à 100)
     */
    private $similarity;

    /**
     * @var array Différences RGB (tableau associatif)
     */
    private $differences;

    /**
     * Constructeur
     * @param string $path
     * @param CRgbPercentage $rgbPercentage
     * @param float $similarity
     * @param array $differences
     * @throws InvalidArgumentException
     */
    public function __construct(string $path, CRgbPercentage $rgbPercentage, float $similarity, array $differences)
    {
        if ($similarity < 0 || $similarity > 100) {
            throw new InvalidArgumentException("Similarity must be between 0 and 100");
        }
        $this->path = $path;
        $this->rgbPercentage = $rgbPercentage;
        $this->similarity = $similarity;
        $this->differences = $differences;
    }

    /**
     * Retourne le chemin de l'image
     * @return string
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * Retourne le nom de fichier de l'image
     * @return string
     */
    public function getFilename(): string
    {
        return basename($this->path);
    }

    /**
     * Retourne les pourcentages RGB de l'image de test
     * @return CRgbPercentage
     */
    public function getRgbPercentage(): CRgbPercentage
    {
        return $this->rgbPercentage;
    }

    /**
     * Retourne le score de similarité
     * @return float
     */
    public function getSimilarity(): float
    {
        return $this->similarity;
    }

    /**
     * Retourne les différences RGB
     * @return array
     */
    public function getDifferences(): array
    {
        return $this->differences;
    }

    /**
     * Retourne un tableau associatif pour affichage
     * @return array
     */
    public function toArray(): array
    {
        return [
            'path' => $this->getPath(),
            'filename' => $this->getFilename(),
            'similarity' => $this->similarity,
            'rgb' => $this->rgbPercentage->toArray(),
            'diff' => $this->differences,
        ];
    }
}
