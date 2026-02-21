<?php
/**
 * Autoloader centralisé du projet MatchRGB
 *
 * Objectif: un seul fichier (pas de Composer, pas de polyfills, pas d'autoload.php)
 * Chargement automatique des classes selon leur emplacement.
 */

spl_autoload_register(static function (string $className): void {
	$baseDir = __DIR__ . '/';

	// Mapping des classes vers leurs fichiers (explicite, rapide)
	$classMap = [
		// Interfaces
		'IImageAnalyzer' => 'Interfaces/IImageAnalyzer.php',
		'IImageLoader' => 'Interfaces/IImageLoader.php',
		'IImageComparator' => 'Interfaces/IImageComparator.php',
		'IApiClient' => 'Interfaces/IApiClient.php',

		// Enumerations
		'EImageType' => 'Enumerations/EImageType.php',

		// Classes Métier
		'CRgbPercentage' => 'Classes/Metier/CRgbPercentage.php',
		'CImageData' => 'Classes/Metier/CImageData.php',
		'CComparisonResult' => 'Classes/Metier/CComparisonResult.php',
		'CPerformanceMonitor' => 'Classes/Metier/CPerformanceMonitor.php',

		// Singletons
		'CConfigurationManager' => 'Classes/Singletons/CConfigurationManager.php',

		// Builders
		'ImageDataBuilder' => 'Classes/Builders/ImageDataBuilder.php',
		'ComparisonResultBuilder' => 'Classes/Builders/ComparisonResultBuilder.php',

		// API
		'GdImageLoader' => 'Classes/Api/GdImageLoader.php',
		'RgbImageAnalyzer' => 'Classes/Api/RgbImageAnalyzer.php',
		'ImageComparator' => 'Classes/Api/ImageComparator.php',
		'UnsplashApiClient' => 'Classes/Api/UnsplashApiClient.php',
	];

	if (isset($classMap[$className])) {
		$file = $baseDir . $classMap[$className];
		if (is_file($file)) {
			require_once $file;
		}
	}
});
