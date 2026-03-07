<?php
/**
 * Autoloader centralisé du projet RGBMatch
 *
 * Convention de nommage :
 * - Prefix C réservé aux Value Objects immutables (CRgbPercentage, CImageData, CComparisonResult).
 * - Les autres classes/services n'ont pas de prefix.
 * - Prefix E pour les Enumerations, I pour les Interfaces.
 *
 * Objectif : un seul fichier (pas de Composer, pas de polyfills).
 *
 * @author  Margot Hourdillé
 * @project RGBMatch — Challenge Firstruner
 * @version 1.0.0
 * @date    2026-03-06
 * @update  2026-03-06
 */

spl_autoload_register(static function (string $className): void {
	$baseDir = __DIR__ . '/';

	$classMap = [
		// Interfaces
		'RGBMatch\\Interfaces\\IImageAnalyzer'          		 => 'Interfaces/IImageAnalyzer.php',
		'RGBMatch\\Interfaces\\IImageLoader'            		 => 'Interfaces/IImageLoader.php',
		'RGBMatch\\Interfaces\\IApiClient'               		 => 'Interfaces/IApiClient.php',
		'RGBMatch\\Interfaces\\IImageDataBuilder'       		 => 'Interfaces/IImageDataBuilder.php',
		'RGBMatch\\Interfaces\\IComparisonResultBuilder' 		 => 'Interfaces/IComparisonResultBuilder.php',
		'RGBMatch\\Interfaces\\IComparisonResultRanker' 		 => 'Interfaces/IComparisonResultRanker.php',
		'RGBMatch\\Interfaces\\IInstrumentedImageAnalyzer' 	 => 'Interfaces/IInstrumentedImageAnalyzer.php',

		// Enumerations
		'RGBMatch\\Enumerations\\EImageType' 								 => 'Enumerations/EImageType.php',

		// Config
		'RGBMatch\\Config\\EnvFileLoader'       						 => 'Classes/Config/EnvFileLoader.php',
		'RGBMatch\\Config\\DirectoryInitializer' 						 => 'Classes/Config/DirectoryInitializer.php',

		// Métier — Value Objects (prefix C)
		'RGBMatch\\Metier\\CRgbPercentage'   								 => 'Classes/Metier/CRgbPercentage.php',
		'RGBMatch\\Metier\\CImageData'        							 => 'Classes/Metier/CImageData.php',
		'RGBMatch\\Metier\\CComparisonResult' 							 => 'Classes/Metier/CComparisonResult.php',

		// Métier — Utilitaires (pas de prefix)
		'RGBMatch\\Metier\\BytesFormatter'     							 => 'Classes/Metier/BytesFormatter.php',
		'RGBMatch\\Metier\\PerformanceMonitor' 							 => 'Classes/Metier/PerformanceMonitor.php',
		'RGBMatch\\Metier\\RamStepMeter'       							 => 'Classes/Metier/RamStepMeter.php',
		'RGBMatch\\Metier\\RamDeltaPresenter' 							 => 'Classes/Metier/RamDeltaPresenter.php',

		// Singletons
		'RGBMatch\\Singletons\\ConfigurationManager' 				 => 'Classes/Singletons/ConfigurationManager.php',

		// Builders
		'RGBMatch\\Builders\\ImageDataBuilder'      				 => 'Classes/Builders/ImageDataBuilder.php',
		'RGBMatch\\Builders\\ComparisonResultBuilder'				 => 'Classes/Builders/ComparisonResultBuilder.php',

		// Application
		'RGBMatch\\Application\\ComparisonResultRanker' 		 => 'Classes/Application/ComparisonResultRanker.php',
		'RGBMatch\\Application\\ServiceFactory'         		 => 'Classes/Application/ServiceFactory.php',
		'RGBMatch\\Application\\ConsoleRenderer'        		 => 'Classes/Application/ConsoleRenderer.php',
		'RGBMatch\\Application\\ResultsPageRenderer'    		 => 'Classes/Application/ResultsPageRenderer.php',
		'RGBMatch\\Application\\IndexVisualPageRenderer' 		 => 'Classes/Application/IndexVisualPageRenderer.php',
		'RGBMatch\\Application\\AnalysisOrchestrator'   		 => 'Classes/Application/AnalysisOrchestrator.php',
		'RGBMatch\\Application\\IsolatedMeasurementPayloadProvider' => 'Classes/Application/IsolatedMeasurementPayloadProvider.php',
		'RGBMatch\\Application\\InstrumentedStep'       		 => 'Classes/Application/InstrumentedStep.php',

		// API
		'RGBMatch\\Api\\GdImageLoader'   										 => 'Classes/Api/GdImageLoader.php',
		'RGBMatch\\Api\\RgbImageAnalyzer' 									 => 'Classes/Api/RgbImageAnalyzer.php',
		'RGBMatch\\Api\\UnsplashApiClient' 									 => 'Classes/Api/UnsplashApiClient.php',
	];

	if (isset($classMap[$className])) {
		$file = $baseDir . $classMap[$className];
		if (is_file($file)) {
			require_once $file;
		}
	}
});
