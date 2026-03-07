<?php
/**
 * Singleton: Gestionnaire de configuration.
 *
 * DI fix : les dépendances (EnvFileLoader, DirectoryInitializer) sont injectées
 * via initializeWith() plutôt qu'instanciées en dur dans le constructeur.
 * En mode par défaut (getInstance()), les implémentations concrètes sont créées.
 * Pour les tests, on peut appeler resetForTesting() puis initializeWith().
 *
 * @author  Margot Hourdillé
 * @project RGBMatch — Challenge Firstruner
 * @version 1.0.0
 * @date    2026-03-06
 * @update  2026-03-06
 */

namespace RGBMatch\Singletons;

use RGBMatch\Config\DirectoryInitializer;
use RGBMatch\Config\EnvFileLoader;
use Exception;
use RuntimeException;

final class ConfigurationManager
{
    /** @var self|null */
    private static $instance = null;
    
    /** @var array */
    private $config = [];

    /** @var EnvFileLoader */
    private $envLoader;

    /** @var DirectoryInitializer */
    private $dirInitializer;
    
    private function __construct(EnvFileLoader $envLoader, DirectoryInitializer $dirInitializer)
    {
        $this->envLoader = $envLoader;
        $this->dirInitializer = $dirInitializer;
        $this->loadEnvironment();
        $this->initializeConfig();
    }
    
    /**
     * Récupération de l'instance unique.
     * Crée les dépendances par défaut si première instanciation.
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self(new EnvFileLoader(), new DirectoryInitializer());
        }
        return self::$instance;
    }

    /**
     * Variante pour tests : permet d'injecter des stubs.
     */
    public static function initializeWith(EnvFileLoader $envLoader, DirectoryInitializer $dirInitializer): self
    {
        self::$instance = new self($envLoader, $dirInitializer);
        return self::$instance;
    }

    /**
     * Reset pour les tests unitaires.
     */
    public static function resetForTesting(): void
    {
        self::$instance = null;
    }
    
    private function __clone() {}
    
    public function __wakeup()
    {
        throw new Exception("Cannot unserialize singleton");
    }
    
    /**
     * Charge les variables d'environnement
     */
    private function loadEnvironment(): void
    {
        $envPath = __DIR__ . '/../../.env';
        
        if (!file_exists($envPath)) {
            throw new RuntimeException("Fichier .env introuvable");
        }

        // Charge et exporte vers putenv/$_ENV/$_SERVER (idempotent).
        $this->envLoader->load($envPath, true);
    }
    
    /**
     * Initialise la configuration
     */
    private function initializeConfig(): void
    {
        $baseDir = dirname(__DIR__, 2);
        
        $this->config = [
            'unsplash' => [
                'access_key' => $_ENV['UNSPLASH_ACCESS_KEY'] ?? '',
                'api_url' => 'https://api.unsplash.com',
            ],
            'paths' => [
                'base' => $baseDir,
                'images' => $baseDir . '/storage/images',
                'origin' => $baseDir . '/storage/images/origin.jpg',
                'test' => $baseDir . '/storage/images/test',
                'results' => $baseDir . '/storage/results',
            ],
        ];

        // Création des dossiers si nécessaire
        $this->dirInitializer->ensure([
            $this->config['paths']['images'],
            $this->config['paths']['test'],
            $this->config['paths']['results'],
        ], 0777);
    }
    
    /**
     * Récupère une valeur de configuration
     */
    public function get(string $key, $default = null)
    {
        $keys = explode('.', $key);
        $value = $this->config;
        
        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return $default;
            }
            $value = $value[$k];
        }
        
        return $value;
    }
    
    /**
     * Définit une valeur de configuration
     */
    public function set(string $key, $value): void
    {
        $keys = explode('.', $key);
        $config = &$this->config;
        
        foreach ($keys as $k) {
            if (!isset($config[$k])) {
                $config[$k] = [];
            }
            $config = &$config[$k];
        }
        
        $config = $value;
    }
}
