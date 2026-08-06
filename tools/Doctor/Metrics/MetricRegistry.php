<?php

declare(strict_types=1);

namespace Tools\Doctor\Metrics;

final class MetricRegistry
{
    /**
     * @var array<string,mixed>
     */
    private static array $metrics = [];

    public static function reset(): void
    {
        self::$metrics = [];
    }

    public static function set(string $name, mixed $value): void
    {
        self::$metrics[$name] = $value;
    }

    public static function add(string $name, mixed $value): void
    {
        self::$metrics[$name] ??= [];

        if (is_array(self::$metrics[$name])) {
            self::$metrics[$name][] = $value;
            return;
        }

        self::$metrics[$name] = $value;
    }

    public static function get(string $name, mixed $default = null): mixed
    {
        return self::$metrics[$name] ?? $default;
    }

    /**
     * @return array<string,mixed>
     */
    public static function all(): array
    {
        return self::$metrics;
    }

    public static function has(string $name): bool
    {
        return array_key_exists($name, self::$metrics);
    }
}
