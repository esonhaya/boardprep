<?php

declare(strict_types=1);

namespace App\Foundation;

class Str
{
    public static function slug(
        string $value
    ): string {

        $value =
            strtolower(
                trim($value)
            );

        $value =
            preg_replace(
                "/[^a-z0-9]+/",
                "-",
                $value
            );

        return trim(
            $value,
            "-"
        );

    }

    public static function title(
        string $value
    ): string {

        return ucwords(
            strtolower(
                $value
            )
        );

    }

    public static function contains(
        string $haystack,
        string $needle
    ): bool {

        return str_contains(
            $haystack,
            $needle
        );

    }
}
