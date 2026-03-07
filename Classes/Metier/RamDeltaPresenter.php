<?php
/**
 * Présentation des deltas mémoire (used/alloc/peak).
 *
 * @author  Margot Hourdillé
 * @project RGBMatch — Challenge Firstruner
 * @version 1.0.0
 * @date    2026-03-06
 * @update  2026-03-06
 */

namespace RGBMatch\Metier;

/**
 * Présente les métriques mémoire sous forme de deltas (used/alloc/peak).
 *
 * SRP: encapsule le calcul/formatage des deltas, sans faire le rendu du tableau.
 */
final class RamDeltaPresenter
{
    /** @var int */
    private $baseUsed = 0;

    /** @var int */
    private $baseAlloc = 0;

    /** @var int */
    private $basePeakAlloc = 0;

    /** @return array{used:int, alloc:int, peakAlloc:int} */
    public function snapshot(): array
    {
        return [
            'used' => memory_get_usage(false),
            'alloc' => memory_get_usage(true),
            'peakAlloc' => memory_get_peak_usage(true),
        ];
    }

    /** @param array{used:int, alloc:int, peakAlloc:int} $snap */
    public function setBaseFromSnapshot(array $snap): void
    {
        $this->baseUsed = max(0, (int) $snap['used']);
        $this->baseAlloc = max(0, (int) $snap['alloc']);
        $this->basePeakAlloc = max(0, (int) $snap['peakAlloc']);
    }

    public function formatSignedBytes(int $bytes): string
    {
        $sign = $bytes < 0 ? '-' : '+';
        return $sign . BytesFormatter::format(abs($bytes));
    }

    public function formatUsedDelta(int $usedBytes): string
    {
        return $this->formatSignedBytes((int) $usedBytes - $this->baseUsed);
    }

    public function formatAllocDelta(int $allocBytes): string
    {
        return $this->formatSignedBytes((int) $allocBytes - $this->baseAlloc);
    }

    public function formatPeakAllocDelta(int $peakAllocBytes): string
    {
        return $this->formatSignedBytes((int) $peakAllocBytes - $this->basePeakAlloc);
    }

    /** @param array{used:int, alloc:int, peakAlloc:int} $snap */
    public function formatLine(array $snap): string
    {
        return sprintf(
            'used=%s | alloc=%s | pic=%s',
            $this->formatUsedDelta((int) $snap['used']),
            $this->formatAllocDelta((int) $snap['alloc']),
            $this->formatPeakAllocDelta((int) $snap['peakAlloc'])
        );
    }
}
