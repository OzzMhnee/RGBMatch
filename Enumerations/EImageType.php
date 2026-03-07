<?php
/**
 * Enumeration des types d'images supportés
 * Convention: Prefix E pour Enumeration
 * Implémentation compatible PHP 7.4+
 *
 * @author  Margot Hourdillé
 * @project RGBMatch — Challenge Firstruner
 * @version 1.0.0
 * @date    2026-03-06
 * @update  2026-03-06
 */

namespace RGBMatch\Enumerations;

final class EImageType
{
    /** @var string */
    private $name;

    /** @var self|null */
    private static $jpeg;

    /** @var self|null */
    private static $png;

    /** @var self|null */
    private static $gif;

    private function __construct(string $name)
    {
        $this->name = $name;
    }

    public static function JPEG(): self
    {
        if (self::$jpeg === null) {
            self::$jpeg = new self('JPEG');
        }
        return self::$jpeg;
    }

    public static function PNG(): self
    {
        if (self::$png === null) {
            self::$png = new self('PNG');
        }
        return self::$png;
    }

    public static function GIF(): self
    {
        if (self::$gif === null) {
            self::$gif = new self('GIF');
        }
        return self::$gif;
    }

    /**
     * Retourne le code GD (IMAGETYPE_*) correspondant.
     */
    public function gdType(): int
    {
        if ($this->name === 'JPEG') {
            return IMAGETYPE_JPEG;
        }
        if ($this->name === 'PNG') {
            return IMAGETYPE_PNG;
        }
        return IMAGETYPE_GIF;
    }
    
    /**
     * Détecte le type d'image depuis un chemin
     */
    public static function fromPath(string $path): ?self
    {
        if (!file_exists($path)) {
            return null;
        }
        
        $imageInfo = getimagesize($path);
        $type = $imageInfo[2] ?? null;
        
        if ($type === IMAGETYPE_JPEG) {
            return self::JPEG();
        }
        if ($type === IMAGETYPE_PNG) {
            return self::PNG();
        }
        if ($type === IMAGETYPE_GIF) {
            return self::GIF();
        }
        
        return null;
    }

    public function getExtension(): string
    {
        if ($this->name === 'JPEG') {
            return 'jpg';
        }
        if ($this->name === 'PNG') {
            return 'png';
        }
        return 'gif';
    }
}
