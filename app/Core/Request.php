<?php

declare(strict_types=1);

namespace App\Core;

class Request
{
    public static function query(
        ?string $key = null,
        mixed $default = null
    ): mixed {
        if ($key === null) {
            return $_GET;
        }

        return $_GET[$key] ?? $default;
    }

    public static function input(
        ?string $key = null,
        mixed $default = null
    ): mixed {
        if ($key === null) {
            return $_POST;
        }

        return $_POST[$key] ?? $default;
    }

    public static function queryString(
        string $key,
        string $default = ''
    ): string {
        $value = self::query($key, $default);

        return is_scalar($value)
            ? trim((string) $value)
            : $default;
    }

    public static function all(): array
    {
        return array_merge(
            $_GET,
            $_POST
        );
    }

    public static function method(): string
    {
        return strtoupper(
            $_SERVER["REQUEST_METHOD"] ?? "GET"
        );
    }

    public static function path(): string
    {
        return parse_url(
            $_SERVER["REQUEST_URI"] ?? "/",
            PHP_URL_PATH
        ) ?: "/";
    }
}
