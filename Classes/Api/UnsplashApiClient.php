<?php
/**
 * Client pour l'API Unsplash
 * Implémente IApiClient
 * Principe: SRP - Responsable uniquement de la communication avec Unsplash
 *
 * @author  Margot Hourdillé
 * @project RGBMatch — Challenge Firstruner
 * @version 1.0.0
 * @date    2026-03-06
 * @update  2026-03-06
 */

namespace RGBMatch\Api;

use Exception;
use RuntimeException;
use RGBMatch\Config\DirectoryInitializer;
use RGBMatch\IO\LockedFileSession;
use RGBMatch\Interfaces\IApiClient;

final class UnsplashApiClient implements IApiClient
{
    /** @var string */
    private $accessKey;
    /** @var string */
    private $apiUrl;
    /** @var string */
    private $downloadPath;

    /** @var string 'stream'|'memory' */
    private $downloadMode;

    /** @var DirectoryInitializer */
    private $directoryInitializer;
    
    public function __construct(string $accessKey, string $apiUrl, string $downloadPath)
    {
        $this->accessKey = $accessKey;
        $this->apiUrl = $apiUrl;
        $this->downloadPath = $downloadPath;

        // Mode de téléchargement:
        // - stream : écrit directement dans un fichier (RAM minimale)
        // - memory : télécharge en string puis file_put_contents (utile pour démo)
        $mode = $_ENV['UNSPLASH_DOWNLOAD_MODE'] ?? getenv('UNSPLASH_DOWNLOAD_MODE') ?? 'stream';
        $mode = strtolower(trim((string) $mode));
        $this->downloadMode = in_array($mode, ['stream', 'memory'], true) ? $mode : 'stream';
        $this->directoryInitializer = new DirectoryInitializer();
        
        // Création du dossier de téléchargement s'il n'existe pas
        $this->ensureDownloadDirectoryExists();
    }
    
    /**
     * Télécharge des images aléatoires
     * 
     * @param int $count Nombre d'images
     * @param string $query Mot-clé de recherche
     * @return array<string> Chemins des images téléchargées
     */
    public function downloadRandomImages(int $count = 10, string $query = ''): array
    {
        if ($count <= 0) {
            return [];
        }

        // Option de vérification SSL configurable via variable d'environnement
        $sslVerify = $_ENV['UNSPLASH_SSL_VERIFY'] ?? '1';
        $sslVerifyLower = strtolower((string) $sslVerify);
        $sslVerifyEnabled = !($sslVerify === '0' || $sslVerifyLower === 'false');

        // Pour éviter les 403 (rate limit), on utilise l'endpoint Unsplash avec count
        $photos = $this->fetchRandomPhotos($count, $query, $sslVerifyEnabled);

        $downloadedPaths = [];
        foreach ($photos as $i => $photo) {
            try {
                if (!is_array($photo) || !isset($photo['urls']['regular'])) {
                    continue;
                }

                $imageUrl = $photo['urls']['regular'];
                $filename = $this->downloadPath . "/image_{$i}_" . uniqid() . ".jpg";

                if ($this->downloadMode === 'memory') {
                    $imageData = $this->downloadBinary($imageUrl, $sslVerifyEnabled);
                    if ($imageData === null) {
                        continue;
                    }
                    if (!$this->writeBinaryFile($filename, $imageData)) {
                        unset($imageData);
                        continue;
                    }
                    unset($imageData);
                } else {
                    if (!$this->downloadToFile($imageUrl, $filename, $sslVerifyEnabled)) {
                        continue;
                    }
                }

                $downloadedPaths[] = $filename;

                // Petit délai pour rester poli
                usleep(50000);
            } catch (Exception $e) {
                error_log("Erreur téléchargement image {$i}: {$e->getMessage()}");
            }
        }

        return $downloadedPaths;
    }

    /**
     * Télécharge des images aléatoires avec une progression en CLI.
     * N'impacte pas l'interface IApiClient.
     *
     * @return array<string>
     */
    public function downloadRandomImagesWithProgress(int $count = 10, string $query = '', string $label = 'Chargement images', bool $showFileSize = true): array
    {
        if ($count <= 0) {
            return [];
        }

        // Option de vérification SSL configurable via variable d'environnement
        $sslVerify = $_ENV['UNSPLASH_SSL_VERIFY'] ?? '1';
        $sslVerifyLower = strtolower((string) $sslVerify);
        $sslVerifyEnabled = !($sslVerify === '0' || $sslVerifyLower === 'false');

        $photos = $this->fetchRandomPhotos($count, $query, $sslVerifyEnabled);
        $total = is_array($photos) ? count($photos) : 0;
        if ($total <= 0) {
            return [];
        }

        $downloadedPaths = [];
        $done = 0;
        $lastSizeInfo = $showFileSize ? ' - 0 Ko' : '';

        echo "{$label}: 0/{$total} (0%)" . $lastSizeInfo;
        if (function_exists('flush')) {
            @flush();
        }
        if (defined('STDOUT')) {
            @fflush(STDOUT);
        }

        foreach ($photos as $i => $photo) {
            $sizeInfo = '';
            try {
                if (is_array($photo) && isset($photo['urls']['regular'])) {
                    $imageUrl = $photo['urls']['regular'];
                    $filename = $this->downloadPath . "/image_{$i}_" . uniqid() . ".jpg";

                    $ok = false;
                    $bytes = 0;
                    if ($this->downloadMode === 'memory') {
                        $imageData = $this->downloadBinary($imageUrl, $sslVerifyEnabled);
                        if ($imageData !== null) {
                            $ok = $this->writeBinaryFile($filename, $imageData);
                            if ($ok) {
                                $bytes = strlen($imageData);
                            }
                            unset($imageData);
                        }
                    } else {
                        $ok = $this->downloadToFile($imageUrl, $filename, $sslVerifyEnabled);
                        if ($ok && $showFileSize && is_file($filename)) {
                            $bytes = (int) filesize($filename);
                        }
                    }

                    if ($ok) {
                        if ($showFileSize && $bytes > 0) {
                            if ($bytes >= 1024 * 1024) {
                                $sizeInfo = ' - ' . number_format($bytes / (1024 * 1024), 2) . ' Mo';
                            } else {
                                $sizeInfo = ' - ' . (int) round($bytes / 1024) . ' Ko';
                            }
                        }
                        $downloadedPaths[] = $filename;
                    }
                }
            } catch (Exception $e) {
                error_log("Erreur téléchargement image {$i}: {$e->getMessage()}");
            }

            $done++;
            $percent = (int) round(($done / $total) * 100);
            if ($showFileSize) {
                $lastSizeInfo = $sizeInfo !== '' ? $sizeInfo : $lastSizeInfo;
            }
            echo "\r{$label}: {$done}/{$total} ({$percent}%)" . ($showFileSize ? $lastSizeInfo : '');
            if (function_exists('flush')) {
                @flush();
            }
            if (defined('STDOUT')) {
                @fflush(STDOUT);
            }

            usleep(50000);
        }

        echo "\n";
        return $downloadedPaths;
    }
    
    /**
     * Télécharge une image spécifique
     * 
     * @param string $query Mot-clé de recherche
     * @return string Chemin de l'image
     */
    public function downloadImage(string $query): string
    {
        $imagePath = $this->downloadSingleImage($query, 'origin');
        
        if (!$imagePath) {
            throw new RuntimeException("Impossible de télécharger l'image");
        }
        
        return $imagePath;
    }
    
    /**
     * Télécharge une seule image
     * 
     * @param string $query Mot-clé
     * @param string|int $index Index ou identifiant
     * @return string|null Chemin de l'image ou null
     */
    private function downloadSingleImage(string $query, $index): ?string
    {
        // Construction de l'endpoint avec ou sans query
        $endpoint = $query 
            ? "{$this->apiUrl}/photos/random?query=" . urlencode($query)
            : "{$this->apiUrl}/photos/random";

        // Option de vérification SSL configurable via variable d'environnement
        $sslVerify = $_ENV['UNSPLASH_SSL_VERIFY'] ?? '1';
        $sslVerifyLower = strtolower((string) $sslVerify);
        $sslVerifyEnabled = !($sslVerify === '0' || $sslVerifyLower === 'false');
        
        // Initialisation de cURL pour l'appel API
        // ch sera fermé automatiquement à la fin du script, mais on s'assure de le faire après chaque appel pour libérer les ressources
        $ch = curl_init();
        // https://www.php.net/manual/fr/function.curl-setopt-array.php
        curl_setopt_array($ch, [
            // Configuration de cURL pour l'appel API
            // CURLOPT_URL pour spécifier l'URL de l'endpoint
            // CURLOPT_RETURNTRANSFER pour retourner la réponse au lieu de l'afficher directement
            // CURLOPT_CONNECTTIMEOUT et CURLOPT_TIMEOUT pour éviter les blocages prolongés
            // CURLOPT_SSL_VERIFYPEER et CURLOPT_SSL_VERIFYHOST pour la sécurité SSL, configurables via variable d'environnement
            // $sslVerifyEnabled ? 2 : 0, pour activer ou désactiver la vérification SSL, 2 pour vérifier le certificat et le nom d'hôte, 0 pour désactiver la vérification
            // CURLOPT_HTTPHEADER pour ajouter les en-têtes nécessaires à l'authentification et à la version de l'API
            CURLOPT_URL => $endpoint,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => $sslVerifyEnabled,
            CURLOPT_SSL_VERIFYHOST => $sslVerifyEnabled ? 2 : 0,
            CURLOPT_HTTPHEADER => [
                "Authorization: Client-ID {$this->accessKey}",
                "Accept-Version: v1",
                "User-Agent: MatchRGB/1.0"
            ]
        ]);
        
        $response = curl_exec($ch);
        if ($response === false) {
            $errorMessage = curl_error($ch);
            $errorCode = curl_errno($ch);
            curl_close($ch);
            throw new RuntimeException("Erreur cURL ({$errorCode}): {$errorMessage}");
        }

        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            $message = "HTTP {$httpCode}";
            $err = json_decode($response, true);
            if (is_array($err)) {
                if (isset($err['errors'][0])) {
                    $message .= " - {$err['errors'][0]}";
                } elseif (isset($err['error'])) {
                    $message .= " - {$err['error']}";
                }
            }
            throw new RuntimeException("Erreur API Unsplash: {$message}");
        }
        
        $data = json_decode($response, true);

        if (!is_array($data)) {
            return null;
        }
        
        // Vérification de la structure de la réponse pour éviter les erreurs d'accès à des clés inexistantes
        if (!isset($data['urls']['regular'])) {
            return null;
        }
        
        // Téléchargement de l'image à partir de l'URL obtenue
        $imageUrl = $data['urls']['regular'];
        $filename = $this->downloadPath . "/image_{$index}_" . uniqid() . ".jpg";

        if ($this->downloadMode === 'memory') {
            $imageData = $this->downloadBinary($imageUrl, $sslVerifyEnabled);
            if ($imageData === null) {
                return null;
            }
            if (!$this->writeBinaryFile($filename, $imageData)) {
                unset($imageData);
                return null;
            }
            unset($imageData);
        } else {
            if (!$this->downloadToFile($imageUrl, $filename, $sslVerifyEnabled)) {
                return null;
            }
        }
        
        return $filename;
    }

    /**
     * Télécharge un binaire directement dans un fichier pour minimiser la RAM.
     *
     * @return bool true si OK, false sinon
     */
    private function downloadToFile(string $url, string $destFile, bool $sslVerifyEnabled): bool
    {
        if (!$this->ensureParentDirectoryExists($destFile)) {
            return false;
        }

        $tempFile = $destFile . '.part';
        if (is_file($tempFile)) {
            @unlink($tempFile);
        }

        $session = new LockedFileSession();
        $ch = null;

        try {
            $session->openExclusiveWrite($tempFile, 'wb');

            $result = $session->process(function ($fp) use ($url, $sslVerifyEnabled, &$ch): array {
                $ch = curl_init();
                // IMPORTANT:
                // En environnement web (Apache/FastCGI), certaines configs font que le corps
                // de réponse de cURL peut "fuiter" vers la sortie HTTP si on ne force pas
                // explicitement l'écriture. On utilise donc CURLOPT_WRITEFUNCTION pour écrire
                // dans le fichier et garantir qu'aucun octet binaire (JPEG) n'est echo.
                curl_setopt_array($ch, [
                    CURLOPT_URL => $url,
                    CURLOPT_RETURNTRANSFER => false,
                    CURLOPT_HEADER => false,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_CONNECTTIMEOUT => 10,
                    CURLOPT_TIMEOUT => 60,
                    CURLOPT_SSL_VERIFYPEER => $sslVerifyEnabled,
                    CURLOPT_SSL_VERIFYHOST => $sslVerifyEnabled ? 2 : 0,
                    CURLOPT_HTTPHEADER => [
                        'User-Agent: MatchRGB/1.0'
                    ],
                ]);

                curl_setopt($ch, CURLOPT_WRITEFUNCTION, static function ($curl, string $data) use ($fp): int {
                    $written = fwrite($fp, $data);
                    return ($written === false) ? 0 : (int) $written;
                });

                $ok = curl_exec($ch);

                return [
                    'ok' => $ok,
                    'httpCode' => (int) curl_getinfo($ch, CURLINFO_HTTP_CODE),
                    'errNo' => curl_errno($ch),
                ];
            });
        } catch (\Throwable $error) {
            if (is_file($tempFile)) {
                @unlink($tempFile);
            }
            return false;
        } finally {
            if ($ch !== null) {
                curl_close($ch);
            }
            $session->close();
        }

        $ok = $result['ok'];
        $httpCode = (int) $result['httpCode'];
        $errNo = (int) $result['errNo'];

        if ($ok === false || $errNo !== 0 || $httpCode < 200 || $httpCode >= 300) {
            @unlink($tempFile);
            return false;
        }

        return $this->finalizeDownloadedFile($tempFile, $destFile);
    }

    private function writeBinaryFile(string $destFile, string $content): bool
    {
        if (!$this->ensureParentDirectoryExists($destFile)) {
            return false;
        }

        $tempFile = $destFile . '.part';
        if (is_file($tempFile)) {
            @unlink($tempFile);
        }

        $session = new LockedFileSession();

        try {
            $session->openExclusiveWrite($tempFile, 'wb');
            $session->process(static function ($handle) use ($content): void {
                fwrite($handle, $content);
                fflush($handle);
            });
        } catch (\Throwable $error) {
            if (is_file($tempFile)) {
                @unlink($tempFile);
            }
            return false;
        } finally {
            $session->close();
        }

        return $this->finalizeDownloadedFile($tempFile, $destFile);
    }

    private function ensureDownloadDirectoryExists(): void
    {
        $result = $this->directoryInitializer->ensure([$this->downloadPath]);
        if (!empty($result['failed']) || !is_dir($this->downloadPath) || !is_writable($this->downloadPath)) {
            throw new RuntimeException('Dossier de telechargement indisponible: ' . $this->downloadPath);
        }
    }

    private function ensureParentDirectoryExists(string $filePath): bool
    {
        $dir = dirname($filePath);
        $result = $this->directoryInitializer->ensure([$dir]);

        return empty($result['failed']) && is_dir($dir) && is_writable($dir);
    }

    private function finalizeDownloadedFile(string $tempFile, string $destFile): bool
    {
        if (!$this->isValidDownloadedImage($tempFile)) {
            @unlink($tempFile);
            return false;
        }

        if (!$this->ensureParentDirectoryExists($destFile)) {
            @unlink($tempFile);
            return false;
        }

        if (is_file($destFile)) {
            @unlink($destFile);
        }

        if (!@rename($tempFile, $destFile)) {
            if (!@copy($tempFile, $destFile)) {
                @unlink($tempFile);
                return false;
            }
            @unlink($tempFile);
        }

        if (!$this->isValidDownloadedImage($destFile)) {
            @unlink($destFile);
            return false;
        }

        return true;
    }

    private function isValidDownloadedImage(string $path): bool
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

        return (int) ($info[0] ?? 0) > 0
            && (int) ($info[1] ?? 0) > 0
            && in_array((int) ($info[2] ?? 0), [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF], true);
    }

    /**
     * Récupère plusieurs photos aléatoires en une seule requête API.
     * Unsplash supporte: /photos/random?count=N&query=...
     *
     * @return array<int, array>
     */
    private function fetchRandomPhotos(int $count, string $query, bool $sslVerifyEnabled): array
    {
        $endpoint = "{$this->apiUrl}/photos/random?count=" . (int) $count;
        if ($query !== '') {
            $endpoint .= "&query=" . urlencode($query);
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $endpoint,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => $sslVerifyEnabled,
            CURLOPT_SSL_VERIFYHOST => $sslVerifyEnabled ? 2 : 0,
            CURLOPT_HTTPHEADER => [
                "Authorization: Client-ID {$this->accessKey}",
                "Accept: application/json",
                "Accept-Version: v1",
                "User-Agent: MatchRGB/1.0"
            ]
        ]);

        $response = curl_exec($ch);
        if ($response === false) {
            $errorMessage = curl_error($ch);
            $errorCode = curl_errno($ch);
            curl_close($ch);
            throw new RuntimeException("Erreur cURL ({$errorCode}): {$errorMessage}");
        }

        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        $rawHeaders = substr($response, 0, $headerSize);
        $body = substr($response, $headerSize);

        $headers = [];
        foreach (preg_split("/\r\n|\n|\r/", (string) $rawHeaders) as $line) {
            $pos = strpos($line, ':');
            if ($pos === false) {
                continue;
            }
            $name = strtolower(trim(substr($line, 0, $pos)));
            $value = trim(substr($line, $pos + 1));
            if ($name !== '') {
                $headers[$name] = $value;
            }
        }

        $data = json_decode($body, true);

        if ($httpCode !== 200) {
            $message = "HTTP {$httpCode}";
            if (is_array($data)) {
                if (isset($data['errors'][0])) {
                    $message .= " - {$data['errors'][0]}";
                } elseif (isset($data['error'])) {
                    $message .= " - {$data['error']}";
                }
            } else {
                $snippet = trim(substr((string) $body, 0, 200));
                if ($snippet !== '') {
                    $message .= " - " . $snippet;
                }
            }

            if (isset($headers['x-ratelimit-remaining']) || isset($headers['x-ratelimit-limit'])) {
                $remaining = $headers['x-ratelimit-remaining'] ?? '?';
                $limit = $headers['x-ratelimit-limit'] ?? '?';
                $message .= " (RateLimit {$remaining}/{$limit})";
            }
            throw new RuntimeException("Erreur API Unsplash: {$message}");
        }

        if (!is_array($data)) {
            return [];
        }

        // Sécurité: si l'API renvoie un objet au lieu d'un tableau
        if (isset($data['id'])) {
            return [$data];
        }

        return $data;
    }

    /**
     * Télécharge des données binaires via cURL (évite la dépendance à allow_url_fopen)
     */
    private function downloadBinary(string $url, bool $sslVerifyEnabled): ?string
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_SSL_VERIFYPEER => $sslVerifyEnabled,
            CURLOPT_SSL_VERIFYHOST => $sslVerifyEnabled ? 2 : 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTPHEADER => [
                'User-Agent: MatchRGB/1.0'
            ]
        ]);

        $data = curl_exec($ch);
        if ($data === false) {
            curl_close($ch);
            return null;
        }

        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode < 200 || $httpCode >= 300) {
            return null;
        }

        return $data;
    }
}
