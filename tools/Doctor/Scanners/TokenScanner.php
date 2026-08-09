<?php

declare(strict_types=1);

namespace Tools\Doctor\Scanners;

final class TokenScanner
{
    /**
     * @return array<int,array{
     *     name:string,
     *     visibility:string,
     *     line:int,
     *     endLine:int,
     *     lines:int
     * }>
     */
    public static function methods(
        string $contents
    ): array {

        $tokens =
            token_get_all(
                $contents
            );

        $methods = [];
        $visibility = "public";
        $count = count($tokens);

        for ($i = 0; $i < $count; $i++) {

            $token = $tokens[$i];

            if (!is_array($token)) {
                continue;
            }

            if (
                self::isVisibilityToken(
                    $token[0]
                )
            ) {

                $visibility =
                    self::visibility(
                        $token[0]
                    );

                continue;

            }

            if (
                $token[0] !== T_FUNCTION
            ) {
                continue;
            }

            $method =
                self::parseMethod(
                    $tokens,
                    $i,
                    $visibility,
                    $count
                );

            if ($method !== null) {
                $methods[] = $method;
            }

            $visibility = "public";

        }

        return $methods;

    }

    private static function isVisibilityToken(
        int $tokenType
    ): bool {

        return in_array(
            $tokenType,
            [
                T_PUBLIC,
                T_PROTECTED,
                T_PRIVATE,
            ],
            true
        );

    }

    private static function visibility(
        int $tokenType
    ): string {

        return match ($tokenType) {

            T_PROTECTED =>
                "protected",

            T_PRIVATE =>
                "private",

            default =>
                "public",

        };

    }

    /**
     * @param array<int,mixed> $tokens
     * @return array{
     *     name:string,
     *     visibility:string,
     *     line:int,
     *     endLine:int,
     *     lines:int
     * }|null
     */
    private static function parseMethod(
        array $tokens,
        int $functionIndex,
        string $visibility,
        int $count
    ): ?array {

        $nameIndex =
            self::findMethodName(
                $tokens,
                $functionIndex + 1,
                $count
            );

        if ($nameIndex === null) {
            return null;
        }

        $name =
            $tokens[$nameIndex][1];

        $startLine =
            $tokens[$nameIndex][2];

        $endLine =
            self::findMethodEndLine(
                $tokens,
                $nameIndex,
                $count,
                $startLine
            );

        return [

            "name" =>
                $name,

            "visibility" =>
                $visibility,

            "line" =>
                $startLine,

            "endLine" =>
                $endLine,

            "lines" =>
                max(
                    1,
                    $endLine - $startLine + 1
                ),

        ];

    }

    /**
     * @param array<int,mixed> $tokens
     */
    private static function findMethodName(
        array $tokens,
        int $start,
        int $count
    ): ?int {

        for (
            $i = $start;
            $i < $count;
            $i++
        ) {

            if (
                is_array($tokens[$i])
                && $tokens[$i][0] === T_STRING
            ) {

                return $i;

            }

            if (
                $tokens[$i] === "("
            ) {

                return null;

            }

        }

        return null;

    }

    /**
     * @param array<int,mixed> $tokens
     */
    private static function findMethodEndLine(
        array $tokens,
        int $start,
        int $count,
        int $defaultLine
    ): int {

        $braceDepth = 0;
        $bodyStarted = false;

        for (
            $i = $start;
            $i < $count;
            $i++
        ) {

            $token =
                $tokens[$i];

            if ($token === "{") {

                $braceDepth++;
                $bodyStarted = true;

                continue;

            }

            if ($token !== "}") {
                continue;
            }

            $braceDepth--;

            if (
                !$bodyStarted
                || $braceDepth !== 0
            ) {
                continue;
            }

            return self::endLine(
                $tokens,
                $i,
                $defaultLine
            );

        }

        return $defaultLine;

    }

    /**
     * @param array<int,mixed> $tokens
     */
    private static function endLine(
        array $tokens,
        int $closingBraceIndex,
        int $defaultLine
    ): int {

        $next =
            $tokens[$closingBraceIndex + 1]
            ?? null;

        if (
            is_array($next)
            && isset($next[2])
        ) {

            return $next[2] - 1;

        }

        return $defaultLine;

    }

    /**
     * @return array<int,string>
     */
    public static function identifiers(
        string $contents
    ): array {

        $identifiers = [];

        foreach (
            token_get_all($contents) as $token
        ) {

            if (
                is_array($token)
                && $token[0] === T_STRING
            ) {

                $identifiers[] =
                    $token[1];

            }

        }

        return array_values(
            array_unique(
                $identifiers
            )
        );

    }

    /**
     * @return array<int,string>
     */
    public static function classes(
        string $contents
    ): array {

        return self::namedDeclarations(
            $contents,
            T_CLASS
        );

    }

    /**
     * @return array<int,string>
     */
    public static function interfaces(
        string $contents
    ): array {

        return self::namedDeclarations(
            $contents,
            T_INTERFACE
        );

    }

    /**
     * @return array<int,string>
     */
    public static function traits(
        string $contents
    ): array {

        return self::namedDeclarations(
            $contents,
            T_TRAIT
        );

    }

    /**
     * @return array<int,string>
     */
    private static function namedDeclarations(
        string $contents,
        int $declarationToken
    ): array {

        $names = [];
        $tokens = token_get_all($contents);
        $count = count($tokens);

        for (
            $i = 0;
            $i < $count;
            $i++
        ) {

            if (
                !is_array($tokens[$i])
                || $tokens[$i][0] !== $declarationToken
            ) {
                continue;
            }

            $name =
                self::findDeclarationName(
                    $tokens,
                    $i + 1,
                    $count
                );

            if ($name !== null) {
                $names[] = $name;
            }

        }

        return $names;

    }

    /**
     * @param array<int,mixed> $tokens
     */
    private static function findDeclarationName(
        array $tokens,
        int $start,
        int $count
    ): ?string {

        for (
            $i = $start;
            $i < $count;
            $i++
        ) {

            if (
                is_array($tokens[$i])
                && $tokens[$i][0] === T_STRING
            ) {

                return $tokens[$i][1];

            }

            if (
                $tokens[$i] === "{"
                || $tokens[$i] === ";"
            ) {

                return null;

            }

        }

        return null;

    }
}
