<?php
/**
 * Interface pour les loaders d'images
 * Convention: Prefix I pour Interface
 *
 * @author  Margot Hourdillé
 * @project RGBMatch — Challenge Firstruner
 * @version 1.0.0
 * @date    2026-03-06
 * @update  2026-03-06
 */

namespace RGBMatch\Interfaces;

use RGBMatch\Enumerations\EImageType;

interface IImageLoader
{
    /**
     * Charge une image depuis un chemin
     * 
     * @param string $imagePath Chemin vers l'image
     * @param EImageType $type Type d'image
     * @return \GdImage|resource|false Image GD ou false en cas d'erreur
     */
    public function load(string $imagePath, EImageType $type);
    
    /**
     * Libère les ressources d'une image
     * 
     * @param \GdImage|resource $image Image à libérer
     */
    public function free($image): void;
}
