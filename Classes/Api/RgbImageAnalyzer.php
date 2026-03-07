<?php
/**
 * Analyseur RGB d'images
 * Implémente IImageAnalyzer
 * Principe: DIP - Dépend d'une abstraction (IImageLoader)
 *
 * @author  Margot Hourdillé
 * @project RGBMatch — Challenge Firstruner
 * @version 1.0.0
 * @date    2026-03-06
 * @update  2026-03-06
 */

namespace RGBMatch\Api;

use InvalidArgumentException;
use RuntimeException;
use RGBMatch\Enumerations\EImageType;
use RGBMatch\Interfaces\IImageLoader;
use RGBMatch\Interfaces\IInstrumentedImageAnalyzer;
use RGBMatch\Metier\CRgbPercentage;

final class RgbImageAnalyzer implements IInstrumentedImageAnalyzer
{
    /** @var IImageLoader */
    private $loader;

    /** @var int */
    private $sampleRate;

    /** @var int */
    private $maxDimension;
    
    /**
     * @param array{sampleRate?:int, maxDimension?:int} $options
     */
    public function __construct(IImageLoader $loader, array $options = [])
    {
        $this->loader = $loader;

        $sr = isset($options['sampleRate']) ? (int) $options['sampleRate'] : 10;
        $this->sampleRate = max(1, $sr);

        $maxDim = isset($options['maxDimension']) ? (int) $options['maxDimension'] : 0;
        $this->maxDimension = max(0, $maxDim);
    }
    
    /**
     * Analyse une image et retourne les pourcentages RGB
     * 
     * @param string $imagePath Chemin vers l'image
     * @return CRgbPercentage Données RGB
     * @throws InvalidArgumentException Si l'image n'existe pas
     * @throws RuntimeException Si l'image ne peut être chargée
     */
    public function analyze(string $imagePath): CRgbPercentage
    {
        if (!file_exists($imagePath)) {
            throw new InvalidArgumentException("L'image n'existe pas : {$imagePath}");
        }
        
        $type = EImageType::fromPath($imagePath);
        if ($type === null) {
            throw new InvalidArgumentException("Type d'image non supporté");
        }
        
        $loadedImage = $this->loader->load($imagePath, $type);

        if ($loadedImage === false) {
            throw new RuntimeException("Impossible de charger l'image : {$imagePath}");
        }

        try {
            $workingImage = $loadedImage;
            $loadedImageToFree = $loadedImage;

            // Option pédagogique: réduire la taille de travail.
            // IMPORTANT: le chargement JPEG/PNG décompresse d'abord l'image en RAM à sa taille réelle.
            // Le downscale ne réduit donc pas le pic au moment du load, mais peut réduire la RAM
            // pendant le calcul et accélérer le traitement.
            if ($this->maxDimension > 0 && function_exists('imagescale')) {
                $w = imagesx($workingImage);
                $h = imagesy($workingImage);
                $maxSide = max($w, $h);

                if ($maxSide > $this->maxDimension) {
                    $ratio = $this->maxDimension / $maxSide;
                    $newW = max(1, (int) round($w * $ratio));
                    $newH = max(1, (int) round($h * $ratio));

                    $scaled = @imagescale($workingImage, $newW, $newH);
                    if ($scaled !== false) {
                        // On libère l'original dès que possible pour limiter la RAM "steady-state".
                        $this->loader->free($workingImage);
                        $loadedImageToFree = null;
                        $workingImage = $scaled;
                    }
                }
            }

            return $this->calculateRgbPercentages($workingImage);
        } finally {
            // Libération de la mémoire (approche pro) : même si une exception survient
            if (isset($loadedImageToFree) && $loadedImageToFree !== null) {
                $this->loader->free($loadedImageToFree);
            }
            if (isset($workingImage) && isset($loadedImage) && $workingImage !== $loadedImage) {
                $this->loader->free($workingImage);
            }
        }
    }

    /**
     * Variante instrumentée sans libération de la ressource GD.
     * Retourne RGB + timings + snapshots mémoire + la ressource GD active.
     *
     * Le CALLER est responsable d'appeler freeGd() sur la ressource retournée.
     * Utile pour contrôler l'instant exact de libération (permutations pédagogiques,
     * pipeline orchestré…) et obtenir des mesures cohérentes entre CLI et web.
     *
     * @param string $imagePath Chemin vers l'image
     * @return array{rgb:CRgbPercentage, stats:array{loadSeconds:float, downscaleSeconds:float, calcSeconds:float, didDownscale:bool, originalW:int, originalH:int, workW:int, workH:int, snaps:array<string, array{used:int,alloc:int,peakAlloc:int}>}, gd:mixed}
     */
    public function analyzeWithStatsKeepGd(string $imagePath): array
    {
        if (!file_exists($imagePath)) {
            throw new InvalidArgumentException("L'image n'existe pas : {$imagePath}");
        }

        $type = EImageType::fromPath($imagePath);
        if ($type === null) {
            throw new InvalidArgumentException('Type d\'image non supporté');
        }

        $stats = [
            'loadSeconds'      => 0.0,
            'downscaleSeconds' => 0.0,
            'calcSeconds'      => 0.0,
            'didDownscale'     => false,
            'originalW'        => 0,
            'originalH'        => 0,
            'workW'            => 0,
            'workH'            => 0,
            'snaps'            => [],
        ];

        $snap = static function (): array {
            return [
                'used'      => memory_get_usage(false),
                'alloc'     => memory_get_usage(true),
                'peakAlloc' => memory_get_peak_usage(true),
            ];
        };

        $stats['snaps']['beforeLoad'] = $snap();

        $t = microtime(true);
        $loadedImage = $this->loader->load($imagePath, $type);
        $stats['loadSeconds'] = microtime(true) - $t;
        $stats['snaps']['afterLoad'] = $snap();

        if ($loadedImage === false) {
            throw new RuntimeException("Impossible de charger l'image : {$imagePath}");
        }

        $workingImage = $loadedImage;

        $stats['originalW'] = (int) imagesx($workingImage);
        $stats['originalH'] = (int) imagesy($workingImage);
        $stats['workW'] = $stats['originalW'];
        $stats['workH'] = $stats['originalH'];

        // Option pédagogique: réduire la taille de travail.
        // IMPORTANT: le chargement JPEG/PNG décompresse d'abord l'image en RAM à sa taille réelle.
        // Le downscale ne réduit donc pas le pic au moment du load, mais peut réduire la RAM
        // pendant le calcul et accélérer le traitement.
        if ($this->maxDimension > 0 && function_exists('imagescale')) {
            $w = $stats['originalW'];
            $h = $stats['originalH'];
            $maxSide = max($w, $h);

            if ($maxSide > $this->maxDimension) {
                $ratio = $this->maxDimension / $maxSide;
                $newW = max(1, (int) round($w * $ratio));
                $newH = max(1, (int) round($h * $ratio));

                $tDown = microtime(true);
                $scaled = @imagescale($workingImage, $newW, $newH);
                $stats['downscaleSeconds'] = microtime(true) - $tDown;

                if ($scaled !== false) {
                    $stats['didDownscale'] = true;
                    $stats['workW'] = (int) imagesx($scaled);
                    $stats['workH'] = (int) imagesy($scaled);

                    // Libère l'original dès que possible ; on travaillera sur la version réduite.
                    $this->loader->free($workingImage);
                    $workingImage = $scaled;
                }
            }
        }

        $stats['snaps']['afterDownscale'] = $stats['didDownscale']
            ? $snap()
            : $stats['snaps']['afterLoad']; // pas de downscale → Δ = 0 B, 0 ms

        $tCalc = microtime(true);
        $rgb = $this->calculateRgbPercentages($workingImage);
        $stats['calcSeconds'] = microtime(true) - $tCalc;
        $stats['snaps']['afterCalc'] = $snap();

        // La libération de $workingImage est laissée au CALLER.
        // → Appeler freeGd($gd) puis $gd = null pour prendre un snapshot fidèle.
        return [
            'rgb'   => $rgb,
            'stats' => $stats,
            'gd'    => $workingImage,
        ];
    }

    /**
     * Libère une ressource GD retournée par analyzeWithStatsKeepGd().
     * Délègue à IImageLoader::free() pour rester compatible PHP 7.4/8+.
     *
     * @param \GdImage|resource|null $gd Ressource à libérer
     */
    public function freeGd($gd): void
    {
        if ($gd === null || $gd === false) {
            return;
        }
        $this->loader->free($gd);
    }

    /**
     * Variante instrumentée pour la formation: renvoie RGB + timings + snapshots mémoire.
     * Appelle analyzeWithStatsKeepGd() puis libère la ressource GD de manière instrumentée.
     *
     * Note: on capture aussi le pic d'allocation (`peakAlloc`) pour pouvoir le visualiser en web.
     *
     * @param string $imagePath Chemin vers l'image
     * @return array{rgb:CRgbPercentage, stats:array{loadSeconds:float, downscaleSeconds:float, calcSeconds:float, freeSeconds:float, didDownscale:bool, originalW:int, originalH:int, workW:int, workH:int, snaps:array<string, array{used:int,alloc:int,peakAlloc:int}>}}
     */
    public function analyzeWithStats(string $imagePath): array
    {
        $result = $this->analyzeWithStatsKeepGd($imagePath);
        $gd     = $result['gd'];
        $rgb    = $result['rgb'];
        $stats  = $result['stats'];
        unset($result); // Retire la référence au GD stockée dans l'array retourné

        // Après unset($result), $gd est la seule variable PHP pointant vers le GdImage.
        // imagedestroy() supprime le bitmap interne ; $gd = null fait tomber le refcount à 0.
        // Le snapshot pris ensuite reflète fidèlement la mémoire libérée.
        $tFree = microtime(true);
        $this->loader->free($gd);
        $gd = null;
        $stats['freeSeconds'] = microtime(true) - $tFree;
        $stats['snaps']['afterFree'] = [
            'used'      => memory_get_usage(false),
            'alloc'     => memory_get_usage(true),
            'peakAlloc' => memory_get_peak_usage(true),
        ];

        return ['rgb' => $rgb, 'stats' => $stats];
    }
    
    /**
     * Calcule les pourcentages RGB d'une image.
     *
     * @param resource|\GdImage $image Image à analyser
     * @return CRgbPercentage Pourcentages RGB
     */
    public function calculateRgbPercentages($image): CRgbPercentage
    {
        $width = imagesx($image);
        $height = imagesy($image);
        
        $redTotal = 0;
        $greenTotal = 0;
        $blueTotal = 0;

        // Échantillonnage (1 pixel sur N)
        $sampleRate = $this->sampleRate;
        $sampledPixels = 0;
        
        for ($x = 0; $x < $width; $x += $sampleRate) {
            for ($y = 0; $y < $height; $y += $sampleRate) {
                $rgb = imagecolorat($image, $x, $y);
                
                $redTotal += ($rgb >> 16) & 0xFF;
                $greenTotal += ($rgb >> 8) & 0xFF;
                $blueTotal += $rgb & 0xFF;
                
                $sampledPixels++;
            }
        }
        
        $maxColorValue = max(1, $sampledPixels) * 255;
        
        return new CRgbPercentage(
            ($redTotal / $maxColorValue) * 100,
            ($greenTotal / $maxColorValue) * 100,
            ($blueTotal / $maxColorValue) * 100
        );
    }
}
