<?php

declare(strict_types=1);

namespace Tools\Doctor\Scanners;

final class EntryPointScanner
{
    public static function isEntryPoint(
        string $path
    ): bool {

        $patterns = [

            "/Controllers/",
            "/Core/App.php",
            "/Core/Router.php",
            "/Core/Config.php",
            "/Core/Env.php",
            "/Core/Storage.php",
            "/Core/Validator.php",
            "/Core/QuestionStorage.php",

        ];

        foreach ($patterns as $pattern) {

            if (str_contains($path, $pattern)) {
                return true;
            }

        }

        return false;
    }
}
