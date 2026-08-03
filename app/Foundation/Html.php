<?php

declare(strict_types=1);

namespace App\Foundation;

class Html
{
    public static function escape(
        mixed $value
    ): string {

        return htmlspecialchars(

            (string) $value,

            ENT_QUOTES,

            "UTF-8"

        );

    }

    public static function checked(
        bool $condition
    ): string {

        return $condition
            ? "checked"
            : "";

    }

    public static function selected(
        bool $condition
    ): string {

        return $condition
            ? "selected"
            : "";

    }
}
