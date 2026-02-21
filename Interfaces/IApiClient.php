<?php
/**
 * Interface pour les clients API
 * Convention: Prefix I pour Interface
 */
interface IApiClient
{
    /**
     * Télécharge des images aléatoires
     * 
     * @param int $count Nombre d'images
     * @param string $query Mot-clé de recherche
     * @return array<string> Chemins des images téléchargées
     */
    public function downloadRandomImages(int $count, string $query = ''): array;
    
    /**
     * Télécharge une image spécifique
     * 
     * @param string $query Mot-clé de recherche
     * @return string Chemin de l'image
     */
    public function downloadImage(string $query): string;
}
