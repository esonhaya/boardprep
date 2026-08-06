<?php

declare(strict_types=1);

namespace Tools\Doctor\Scanners;

final class PhpSourceScanner
{
    public static function contents(
        string $file
    ): string {

        $contents = file_get_contents(
            $file
        );

        return $contents === false
            ? ""
            : $contents;

    }

    public static function lineCount(
        string $contents
    ): int {

        if ($contents === "") {
            return 0;
        }

        return substr_count(
            $contents,
            "\n"
        ) + 1;

    }

    public static function classes(
        string $contents
    ): array {

        preg_match_all(
            '/^(?:final\s+|abstract\s+)?class\s+([A-Za-z0-9_]+)/m',
            $contents,
            $matches
        );

        return $matches[1];

    }

    public static function interfaces(
        string $contents
    ): array {

        preg_match_all(
            '/^interface\s+([A-Za-z0-9_]+)/m',
            $contents,
            $matches
        );

        return $matches[1];

    }

    public static function traits(
        string $contents
    ): array {

        preg_match_all(
            '/^trait\s+([A-Za-z0-9_]+)/m',
            $contents,
            $matches
        );

        return $matches[1];

    }

    public static function namespace(
        string $contents
    ): ?string {

        preg_match(
            '/^namespace\s+([^;]+);/m',
            $contents,
            $matches
        );

        return $matches[1] ?? null;

    }

    public static function imports(
        string $contents
    ): array {

        preg_match_all(
            '/^use\s+([^;]+);/m',
            $contents,
            $matches
        );

        return $matches[1];

    }

    public static function methods(
        string $contents
    ): array {

        preg_match_all(
            '/^(public|protected|private)?\s*(?:static\s+)?function\s+([A-Za-z0-9_]+)\s*\(/m',
            $contents,
            $matches,
            PREG_SET_ORDER
        );

        return $matches;

    }
}
