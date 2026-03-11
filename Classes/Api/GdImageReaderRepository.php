<?php
/**
 * Repository en lecture seule des readers GD par type d'image.
 *
 * Pattern : RepositoryReader.
 * Le mapping type -> fonction GD est centralisé ici ; le loader ne fait
 * plus qu'orchestrer la lecture et la libération des ressources.
 *
 * @author  Margot Hourdillé
 * @project RGBMatch — Challenge Firstruner
 * @version 1.0.0
 * @date    2026-03-11
 */

namespace RGBMatch\Api;

use RGBMatch\Enumerations\EImageType;
use RGBMatch\Interfaces\IImageReaderRepository;

final class GdImageReaderRepository implements IImageReaderRepository
{
    /**
     * @var array<int, callable(string): \GdImage|resource|false>
     */
    private static $readers = [];

    /**
     * @return array<int, callable(string): \GdImage|resource|false>
     */
    private static function getReaders(): array
    {
        if (empty(self::$readers)) {
            self::$readers = [
                IMAGETYPE_JPEG => 'imagecreatefromjpeg',
                IMAGETYPE_PNG  => 'imagecreatefrompng',
                IMAGETYPE_GIF  => 'imagecreatefromgif',
            ];
        }

        return self::$readers;
    }

    public function findByType(EImageType $type): ?callable
    {
        $typeValue = $type->gdType();
        $readers = self::getReaders();

        return isset($readers[$typeValue]) ? $readers[$typeValue] : null;
    }
}