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

final class GdImageLoader implements IImageLoader
{
    /**
     * Registre type GD → callback de chargement.
     * Ajouter un type = ajouter une entrée (OCP).
     *
     * @var array<int, callable(string): \GdImage|resource|false>
     */
    private static $loaders = [];

    /**
     * Retourne le registre des loaders (initialisé une seule fois).
     */
    private static function getLoaders(): array
    {
        if (empty(self::$loaders)) {
            self::$loaders = [
                IMAGETYPE_JPEG => 'imagecreatefromjpeg',
                IMAGETYPE_PNG  => 'imagecreatefrompng',
                IMAGETYPE_GIF  => 'imagecreatefromgif',
            ];
        }
        return self::$loaders;
    }

    /**
     * Charge une image selon son type (Strategy map).
     *
     * @param string    $imagePath Chemin vers l'image
     * @param EImageType $type     Type d'image
     * @return \GdImage|resource|false Image chargée ou false
     */
    public function load(string $imagePath, EImageType $type)
    {
        $loaders = self::getLoaders();
        $typeValue = $type->gdType();

        if (!isset($loaders[$typeValue])) {
            return false;
        }

        return ($loaders[$typeValue])($imagePath);
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
        if (!is_array($info)) {
            return ['w' => 0, 'h' => 0];
        }
        return ['w' => (int) ($info[0] ?? 0), 'h' => (int) ($info[1] ?? 0)];
    }
}
