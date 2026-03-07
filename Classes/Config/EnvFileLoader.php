<?php
/**
 * Chargement d'un fichier .env simple (KEY=VALUE).
 *
 * @author  Margot Hourdillé
 * @project RGBMatch — Challenge Firstruner
 * @version 1.0.0
 * @date    2026-03-06
 * @update  2026-03-06
 */

namespace RGBMatch\Config;

/**
 * Charge un fichier .env simple (KEY=VALUE), sans dépendance externe.
 * SRP: parse + export (putenv/$_ENV/$_SERVER) de façon idempotente.
 */
final class EnvFileLoader
{
    /**
     * @return array<string, string>
     */
    public function load(string $envFilePath, bool $exportToSuperglobals = true): array
    {
        if (!is_file($envFilePath) || !is_readable($envFilePath)) {
            return [];
        }

        $lines = @file($envFilePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!is_array($lines)) {
            return [];
        }

        $values = [];

        foreach ($lines as $line) {
            $line = trim($line);
            $isComment = false;
            if (function_exists('str_starts_with')) {
                $isComment = \str_starts_with($line, '#');
            } else {
                $isComment = substr($line, 0, 1) === '#';
            }

            if ($line === '' || $isComment) {
                continue;
            }

            $pos = strpos($line, '=');
            if ($pos === false) {
                continue;
            }

            $key = trim(substr($line, 0, $pos));
            $rawValue = trim(substr($line, $pos + 1));

            if ($key === '') {
                continue;
            }

            $value = $this->unquote($rawValue);
            $values[$key] = $value;

            if ($exportToSuperglobals) {
                // Keep behavior close to typical dotenv: env + superglobals.
                @putenv($key . '=' . $value);
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
            }
        }

        return $values;
    }

    private function unquote(string $value): string
    {
        if ($value === '') {
            return '';
        }

        $first = $value[0];
        $last = $value[strlen($value) - 1];
        if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
            $inner = substr($value, 1, -1);
            // Minimal unescaping for common sequences.
            $inner = str_replace(['\\n', '\\r', '\\t'], ["\n", "\r", "\t"], $inner);
            $inner = str_replace(['\\"', "\\'", '\\\\'], ['"', "'", '\\'], $inner);
            return $inner;
        }

        return $value;
    }
}
