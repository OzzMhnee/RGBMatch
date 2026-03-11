<?php
/**
 * Limiteur de requetes simple base sur un store JSON verrouille.
 *
 * Utilise une fenetre glissante et un cooldown optionnel pour proteger
 * des endpoints sensibles ou couteux.
 *
 * @author  Margot Hourdillé
 * @project RGBMatch — Challenge Firstruner
 * @version 1.0.0
 * @date    2026-03-11
 */

namespace RGBMatch\Security;

use RGBMatch\Interfaces\IJsonFileStore;

final class RequestThrottle
{
    /** @var IJsonFileStore */
    private $store;

    /** @var string */
    private $stateFile;

    public function __construct(IJsonFileStore $store, string $stateFile)
    {
        $this->store = $store;
        $this->stateFile = $stateFile;
    }

    /**
     * @return array{allowed:bool,retryAfter:int}
     */
    public function hit(string $bucket, int $maxHits, int $windowSeconds, int $cooldownSeconds = 0): array
    {
        $now = time();
        $state = $this->store->read($this->stateFile);

        if (!is_array($state)) {
            $state = ['buckets' => []];
        }

        if (!isset($state['buckets']) || !is_array($state['buckets'])) {
            $state['buckets'] = [];
        }

        $entry = isset($state['buckets'][$bucket]) && is_array($state['buckets'][$bucket])
            ? $state['buckets'][$bucket]
            : ['hits' => [], 'blockedUntil' => 0];

        $blockedUntil = isset($entry['blockedUntil']) ? (int) $entry['blockedUntil'] : 0;
        if ($blockedUntil > $now) {
            return [
                'allowed' => false,
                'retryAfter' => max(1, $blockedUntil - $now),
            ];
        }

        $hits = [];
        if (isset($entry['hits']) && is_array($entry['hits'])) {
            foreach ($entry['hits'] as $timestamp) {
                $timestamp = (int) $timestamp;
                if ($timestamp >= ($now - $windowSeconds)) {
                    $hits[] = $timestamp;
                }
            }
        }

        $hits[] = $now;

        if (count($hits) > $maxHits) {
            $entry['hits'] = $hits;
            $entry['blockedUntil'] = $cooldownSeconds > 0 ? ($now + $cooldownSeconds) : ($now + $windowSeconds);
            $state['buckets'][$bucket] = $entry;
            $this->store->write($this->stateFile, $state);

            return [
                'allowed' => false,
                'retryAfter' => max(1, ((int) $entry['blockedUntil']) - $now),
            ];
        }

        $entry['hits'] = $hits;
        $entry['blockedUntil'] = 0;
        $state['buckets'][$bucket] = $entry;
        $this->store->write($this->stateFile, $state);

        return [
            'allowed' => true,
            'retryAfter' => 0,
        ];
    }
}