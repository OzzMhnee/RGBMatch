<?php
/**
 * Classe Métier: Pourcentages RGB d'une image
 * Convention: Prefix C pour Classe Métier
 * Principe: Immutabilité pour éviter les effets de bord
 *
 * @author  Margot Hourdillé
 * @project RGBMatch — Challenge Firstruner
 * @version 1.0.0
 * @date    2026-03-06
 * @update  2026-03-06
 */

namespace RGBMatch\Metier;

final class CRgbPercentage
{
    private $red;
    private $green;
    private $blue;
    
    public function __construct(float $red, float $green, float $blue)
    {
        $this->red = $red;
        $this->green = $green;
        $this->blue = $blue;
        $this->validate();
    }
    
    /**
     * Validation des pourcentages
     */
    private function validate(): void
    {
        if ($this->red < 0 || $this->red > 100) {
            throw new \InvalidArgumentException("Red percentage must be between 0 and 100");
        }
        if ($this->green < 0 || $this->green > 100) {
            throw new \InvalidArgumentException("Green percentage must be between 0 and 100");
        }
        if ($this->blue < 0 || $this->blue > 100) {
            throw new \InvalidArgumentException("Blue percentage must be between 0 and 100");
        }
    }
    
    public function getRed(): float
    {
        return $this->red;
    }
    
    public function getGreen(): float
    {
        return $this->green;
    }
    
    public function getBlue(): float
    {
        return $this->blue;
    }
    
    /**
     * Calcule la différence avec un autre ensemble RGB
     */
    public function calculateDifference(self $other): array
    {
        return [
            'r' => abs($this->red - $other->getRed()),
            'g' => abs($this->green - $other->getGreen()),
            'b' => abs($this->blue - $other->getBlue()),
        ];
    }
    
    /**
     * Calcule le score de similarité avec un autre ensemble RGB
     * Score de 0 à 100 (100 = identique)
     */
    public function calculateSimilarity(self $other): float
    {
        $diff = $this->calculateDifference($other);
        $totalDiff = $diff['r'] + $diff['g'] + $diff['b'];
        
        return max(0, 100 - ($totalDiff / 3));
    }
    
    /**
     * Retourne un tableau associatif
     */
    public function toArray(): array
    {
        return [
            'r' => $this->red,
            'g' => $this->green,
            'b' => $this->blue,
        ];
    }
}
