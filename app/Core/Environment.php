<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

final class Environment
{
    public static function load(string $path): void
    {
        if (!is_file($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            throw new RuntimeException("Unable to read environment file: {$path}");
        }

        foreach ($lines as $number => $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (str_starts_with($line, 'export ')) {
                $line = trim(substr($line, 7));
            }

            if (!str_contains($line, '=')) {
                $lineNumber = $number + 1;
                throw new RuntimeException("Malformed environment entry on line {$lineNumber}.");
            }

            [$key, $value] = array_map('trim', explode('=', $line, 2));
            if (preg_match('/^[A-Z_][A-Z0-9_]*$/', $key) !== 1) {
                $lineNumber = $number + 1;
                throw new RuntimeException("Invalid environment key on line {$lineNumber}.");
            }

            if (self::has($key)) {
                continue;
            }

            $_ENV[$key] = self::unquote($value, $number + 1);
        }
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        if (array_key_exists($key, $_ENV)) {
            return is_scalar($_ENV[$key]) ? (string) $_ENV[$key] : $default;
        }

        $value = getenv($key);
        return $value === false ? $default : $value;
    }

    private static function has(string $key): bool
    {
        return array_key_exists($key, $_ENV) || getenv($key) !== false;
    }

    private static function unquote(string $value, int $lineNumber): string
    {
        if ($value === '') {
            return '';
        }

        $first = $value[0];
        if ($first !== '"' && $first !== "'") {
            return $value;
        }

        if (strlen($value) < 2 || substr($value, -1) !== $first) {
            throw new RuntimeException("Unclosed quoted value on environment line {$lineNumber}.");
        }

        return substr($value, 1, -1);
    }
}
