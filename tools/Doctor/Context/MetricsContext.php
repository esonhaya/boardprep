<?php

declare(strict_types=1);

namespace Tools\Doctor\Context;

final class MetricsContext
{
    private static array $metrics = [];

    public static function set(string $key, mixed $value): void
    {
        self::$metrics[$key] = $value;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::$metrics[$key] ?? $default;
    }

    public static function has(string $key): bool
    {
        return array_key_exists($key, self::$metrics);
    }

    public static function clear(): void
    {
        self::$metrics = [];
    }
}
