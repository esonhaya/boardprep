<?php

declare(strict_types=1);

namespace App\Foundation;

class Arr
{
    public static function get(
        array $array,
        string|int $key,
        mixed $default = null
    ): mixed {

        return $array[$key]
            ?? $default;

    }

    public static function has(
        array $array,
        string|int $key
    ): bool {

        return array_key_exists(
            $key,
            $array
        );

    }

    public static function first(
        array $array,
        mixed $default = null
    ): mixed {

        return empty($array)
            ? $default
            : reset($array);

    }

    public static function last(
        array $array,
        mixed $default = null
    ): mixed {

        return empty($array)
            ? $default
            : end($array);

    }

    public static function pluck(
        array $array,
        string $key
    ): array {

        return array_map(

            static fn(
                array $item
            ) =>
                $item[$key]
                ?? null,

            $array

        );

    }
}
