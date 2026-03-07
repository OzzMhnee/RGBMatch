<?php
/**
 * Classe pour monitorer les performances
 * Principe: SRP - Responsable uniquement du monitoring
 *
 * @author  Margot Hourdillé
 * @project RGBMatch — Challenge Firstruner
 * @version 1.0.0
 * @date    2026-03-06
 * @update  2026-03-06
 */

namespace RGBMatch\Metier;

final class PerformanceMonitor
{
    /** @var float */
    private $startTime;
    /** @var int */
    private $startUsed;
    /** @var int */
    private $startAlloc;
    /** @var int */
    private $startPeakAlloc;
    /** @var array */
    private $checkpoints = [];

    public function start(): void
    {
        $this->startTime = microtime(true);
        $this->startUsed = memory_get_usage(false);
        $this->startAlloc = memory_get_usage(true);
        $this->startPeakAlloc = memory_get_peak_usage(true);
    }

    /**
     * Ajoute un checkpoint de performance
     */
    public function checkpoint(string $label): void
    {
        $this->checkpoints[] = [
            'label' => $label,
            'time' => microtime(true) - $this->startTime,
            // "used" est plus parlant que l'allocation brute (et évite un delta à 0)
            'used' => memory_get_usage(false),
            'alloc' => memory_get_usage(true),
            'peak_alloc' => memory_get_peak_usage(true)
        ];
    }

    /**
     * Génère un rapport de performance
     */
    public function getReport(): array
    {
        $totalTime = microtime(true) - $this->startTime;
        $endUsed = memory_get_usage(false);
        $endAlloc = memory_get_usage(true);
        $peakAlloc = memory_get_peak_usage(true);

        return [
            'total_time' => $totalTime,
            'start_used' => $this->startUsed,
            'start_alloc' => $this->startAlloc,
            'end_used' => $endUsed,
            'end_alloc' => $endAlloc,
            'peak_alloc' => $peakAlloc,
            'checkpoints' => $this->checkpoints
        ];
    }

    /**
     * Formate le rapport sous forme de chaîne (SRP : pas d'echo, retourne les données).
     * L'appelant décide quoi faire du texte (echo, log, etc.).
     */
    public function formatReport(): string
    {
        $report = $this->getReport();

        $deltaEndUsed = $report['end_used'] - $report['start_used'];
        $deltaPeakAlloc = $report['peak_alloc'] - $this->startPeakAlloc;

        $lines = [];
        $lines[] = "\n=== RAPPORT DE PERFORMANCE ===";
        $lines[] = sprintf("Temps total: %.3f secondes", $report['total_time']);
        $lines[] = sprintf(
            "ΔRAM (début -> fin): +0 B -> %s",
            '+' . BytesFormatter::format(max(0, $deltaEndUsed))
        );
        $lines[] = sprintf("Δpic RAM (PHP): %s", '+' . BytesFormatter::format(max(0, $deltaPeakAlloc)));

        if (!empty($report['checkpoints'])) {
            $lines[] = "\nCheckpoints:";
            foreach ($report['checkpoints'] as $cp) {
                $deltaUsed = $cp['used'] - $report['start_used'];
                $lines[] = sprintf(
                    "  - %s: %.3fs | ΔRAM=%s",
                    $cp['label'],
                    $cp['time'],
                    '+' . BytesFormatter::format(max(0, $deltaUsed))
                );
            }
        }
        $lines[] = "==============================\n";

        return implode("\n", $lines);
    }

    /**
     * Affiche le rapport (raccourci rétrocompatible, délègue à formatReport).
     */
    public function displayReport(): void
    {
        echo $this->formatReport();
    }
}
