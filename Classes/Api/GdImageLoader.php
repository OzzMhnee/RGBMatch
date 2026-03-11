<?php
/**
 * Implémentation du loader d'images (GD).
 *
 * Principe : SRP – Responsable uniquement du chargement/libération d'images.
 * OCP fix  : Strategy map au lieu d'un if/elseif (ajouter un type = ajouter une entrée).
 *
 * @author  Margot Hourdillé
 * @project RGBMatch — Challenge Firstruner
 * @version 1.0.0
 * @date    2026-03-06
 * @update  2026-03-06
 */

namespace RGBMatch\Api;

use RGBMatch\Enumerations\EImageType;
use RGBMatch\Interfaces\IImageLoader;
use RGBMatch\Interfaces\IImageReaderRepository;

final class GdImageLoader implements IImageLoader
{
    /**
     * @var IImageReaderRepository
     */
    private $readerRepository;

    public function __construct(IImageReaderRepository $readerRepository)
    {
        $this->readerRepository = $readerRepository;
    }

    /**
    * Charge une image selon son type via un repository de readers.
     *
     * @param string    $imagePath Chemin vers l'image
     * @param EImageType $type     Type d'image
     * @return \GdImage|resource|false Image chargée ou false
     */
    public function load(string $imagePath, EImageType $type)
    {
        $reader = $this->readerRepository->findByType($type);
        if ($reader === null) {
            return false;
        }

        return $reader($imagePath);
    }
    
    /**
     * Libère les ressources d'une image.
     */
    public function free($image): void
    {
        if ($image === null || $image === false) {
            return;
        }

        // Compatible PHP 7.4 (resource) et PHP 8+ (objet GdImage).
        if (function_exists('imagedestroy')) {
            @imagedestroy($image);
        }
    }

    /**
     * Estime la RAM qu'un bitmap brut (W×H×4 octets RGBA) consomme.
     */
    public static function estimateRamBytes(int $w, int $h): int
    {
        return max(0, $w) * max(0, $h) * 4;
    }

    /**
     * Récupère les dimensions d'une image sans la charger en mémoire GD.
     *
     * @return array{w:int, h:int}
     */
    public static function getImageMeta(string $path): array
    {
        $info = @getimagesize($path);
        return [
            'w' => (int) ($info[0] ?? 0), 
            'h' => (int) ($info[1] ?? 0)
        ];
    }
}
