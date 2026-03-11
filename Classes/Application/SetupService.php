<?php
/**
 * Service applicatif de setup des images RGBMatch.
 *
 * Ordonne: verification des dossiers, nettoyage, telechargement de l'origine,
 * telechargement des images de test, validation des fichiers et nettoyage sur echec.
 *
 * @author  Margot Hourdillé
 * @project RGBMatch — Challenge Firstruner
 * @version 1.0.0
 * @date    2026-03-11
 */

namespace RGBMatch\Application;

use RuntimeException;
use RGBMatch\Api\UnsplashApiClient;
use RGBMatch\Config\DirectoryInitializer;

final class SetupService
{
    /** @var UnsplashApiClient */
    private $apiClient;

    /** @var DirectoryInitializer */
    private $directoryInitializer;

    /** @var string */
    private $testDir;

    /** @var string */
    private $originPath;

    public function __construct(
        UnsplashApiClient $apiClient,
        string $testDir,
        string $originPath,
        ?DirectoryInitializer $directoryInitializer = null
    ) {
        $this->apiClient = $apiClient;
        $this->testDir = $testDir;
        $this->originPath = $originPath;
        $this->directoryInitializer = $directoryInitializer ?: new DirectoryInitializer();
    }

    /**
     * @return array{downloadedTests:int, query:string, originWritten:bool}
     */
    public function run(int $count, string $query = '', bool $withProgress = false): array
    {
        $this->ensureDirectoriesReady();
        $this->cleanupExistingImages();

        try {
            $originPaths = $this->downloadImages(1, $query, $withProgress, 'Chargement origine');
            if (!isset($originPaths[0]) || !$this->moveImageFile($originPaths[0], $this->originPath)) {
                throw new RuntimeException("Impossible de finaliser l'image d'origine.");
            }

            $testPaths = $this->downloadImages($count, $query, $withProgress, 'Chargement images test');
            if (count($testPaths) < $count) {
                throw new RuntimeException(
                    'Telechargement incomplet: ' . count($testPaths) . '/' . $count . ' image(s) valides seulement.'
                );
            }

            return [
                'downloadedTests' => count($testPaths),
                'query' => $query,
                'originWritten' => true,
            ];
        } catch (\Throwable $error) {
            $this->cleanupExistingImages();
            throw $error;
        }
    }

    private function ensureDirectoriesReady(): void
    {
        $directories = [
            $this->testDir,
            dirname($this->originPath),
        ];

        $result = $this->directoryInitializer->ensure($directories);
        if (!empty($result['failed'])) {
            throw new RuntimeException('Impossible de preparer les dossiers de destination.');
        }

        foreach ($directories as $dir) {
            if (!is_dir($dir) || !is_writable($dir)) {
                throw new RuntimeException('Dossier indisponible ou non inscriptible: ' . $dir);
            }
        }
    }

    private function cleanupExistingImages(): void
    {
        $oldImages = glob($this->testDir . '/*.jpg');
        if (is_array($oldImages)) {
            foreach ($oldImages as $oldImage) {
                if (is_file($oldImage) && !@unlink($oldImage)) {
                    throw new RuntimeException('Impossible de supprimer un ancien fichier de test.');
                }
            }
        }

        $tmpImages = glob($this->testDir . '/*.part');
        if (is_array($tmpImages)) {
            foreach ($tmpImages as $tmpImage) {
                if (is_file($tmpImage)) {
                    @unlink($tmpImage);
                }
            }
        }

        if ($this->originPath !== '' && is_file($this->originPath) && !@unlink($this->originPath)) {
            throw new RuntimeException("Impossible de supprimer l'ancienne image d'origine.");
        }
    }

    /**
     * @return string[]
     */
    private function downloadImages(int $count, string $query, bool $withProgress, string $label): array
    {
        $paths = $withProgress
            ? $this->apiClient->downloadRandomImagesWithProgress($count, $query, $label, true)
            : $this->apiClient->downloadRandomImages($count, $query);

        $validPaths = [];
        foreach ($paths as $path) {
            if (!is_string($path) || !$this->isValidImageFile($path)) {
                if (is_string($path) && is_file($path)) {
                    @unlink($path);
                }
                continue;
            }
            $validPaths[] = $path;
        }

        return $validPaths;
    }

    private function moveImageFile(string $from, string $to): bool
    {
        $this->ensureDirectoriesReady();

        if (!is_file($from) || !$this->isValidImageFile($from)) {
            return false;
        }

        if (@rename($from, $to)) {
            return $this->isValidImageFile($to);
        }

        if (@copy($from, $to)) {
            @unlink($from);
            return $this->isValidImageFile($to);
        }

        return false;
    }

    private function isValidImageFile(string $path): bool
    {
        if (!is_file($path) || !is_readable($path)) {
            return false;
        }

        $size = (int) @filesize($path);
        if ($size <= 1024) {
            return false;
        }

        $info = @getimagesize($path);
        if (!is_array($info)) {
            return false;
        }

        $width = (int) ($info[0] ?? 0);
        $height = (int) ($info[1] ?? 0);
        $type = (int) ($info[2] ?? 0);

        return $width > 0
            && $height > 0
            && in_array($type, [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF], true);
    }
}