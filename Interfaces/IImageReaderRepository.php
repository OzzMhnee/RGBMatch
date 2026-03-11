<?php
/**
 * Contrat de consultation du registre des readers d'images.
 *
 * Le repository expose uniquement de la lecture : il fournit le reader
 * adapté à un type d'image, sans exécuter lui-même le chargement.
 *
 * @author  Margot Hourdillé
 * @project RGBMatch — Challenge Firstruner
 * @version 1.0.0
 * @date    2026-03-11
 */

namespace RGBMatch\Interfaces;

use RGBMatch\Enumerations\EImageType;

interface IImageReaderRepository
{
    /**
     * Retourne le reader GD associé à un type d'image.
     *
     * @return callable(string): \GdImage|resource|false|null
     */
    public function findByType(EImageType $type): ?callable;
}