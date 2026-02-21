<?php
/**
 * Singleton: Gestionnaire de configuration
 * Convention: Prefix C pour Singleton
 * Pattern: Singleton pour instance unique
 */
final class CConfigurationManager
{
    /** @var self|null */
    private static $instance = null;
    
    /** @var array */
    private $config = [];
    
    /**
     * Constructeur privé (Singleton)
     */
    private function __construct()
    {
        $this->loadEnvironment();
        $this->initializeConfig();
    }
    
    /**
     * Récupération de l'instance unique
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        
        return self::$instance;
    }
    
    /**
     * Empêche le clonage
     */
    private function __clone() {}
    
    /**
     * Empêche la désérialisation
     */
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
        
        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) {
                continue;
            }
            
            if (strpos($line, '=') === false) {
                continue;
            }
            
            list($name, $value) = explode('=', $line, 2);
            $_ENV[trim($name)] = trim($value);
        }
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
        foreach ($this->config['paths'] as $key => $path) {
            if ($key === 'base' || $key === 'origin') {
                continue;
            }
            
            if (!is_dir($path)) {
                mkdir($path, 0777, true);
            }
        }
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
