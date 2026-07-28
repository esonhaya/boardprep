<?php

class Env
{
    private static bool $loaded = false;

    public static function load(
        string $file
    ): void {

        if (self::$loaded || !file_exists($file)) {
            return;
        }

        foreach (
            file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)
            as $line
        ) {

            $line = trim($line);

            if (
                $line === ""
                || str_starts_with($line, "#")
            ) {
                continue;
            }

            [$key, $value] =
                array_pad(
                    explode("=", $line, 2),
                    2,
                    ""
                );

            $_ENV[trim($key)] =
                trim($value);
        }

        self::$loaded = true;
    }

    public static function get(
        string $key,
        mixed $default = null
    ): mixed {

        return $_ENV[$key] ?? $default;

    }
}
