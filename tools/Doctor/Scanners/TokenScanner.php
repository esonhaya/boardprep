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

            switch ($token[0]) {

                case T_PUBLIC:
                    $visibility = "public";
                    break;

                case T_PROTECTED:
                    $visibility = "protected";
                    break;

                case T_PRIVATE:
                    $visibility = "private";
                    break;

                case T_FUNCTION:

                    $j = $i + 1;

                    while ($j < $count) {

                        if (
                            is_array($tokens[$j])
                            && $tokens[$j][0] === T_STRING
                        ) {
                            break;
                        }

                        $j++;

                    }

                    if ($j >= $count) {
                        continue 2;
                    }

                    $name =
                        $tokens[$j][1];

                    $startLine =
                        $tokens[$j][2];

                    $braceDepth = 0;
                    $bodyStarted = false;
                    $endLine = $startLine;

                    for ($k = $j; $k < $count; $k++) {

                        $current = $tokens[$k];

                        if ($current === "{") {

                            $braceDepth++;
                            $bodyStarted = true;

                        }

                        if ($current === "}") {

                            $braceDepth--;

                            if (
                                $bodyStarted
                                && $braceDepth === 0
                            ) {

                                if (
                                    isset($tokens[$k + 1])
                                    && is_array($tokens[$k + 1])
                                ) {

                                    $endLine =
                                        $tokens[$k + 1][2] - 1;

                                }

                                break;

                            }

                        }

                    }

                    $methods[] = [

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

                    $visibility = "public";

                    break;

            }

        }

        return $methods;

    }

    /**
     * @return array<int,string>
     */
    public static function identifiers(
        string $contents
    ): array {

        $identifiers = [];

        foreach (

            token_get_all(
                $contents
            )

            as $token

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

        $classes = [];
        $tokens = token_get_all($contents);
        $count = count($tokens);

        for ($i = 0; $i < $count; $i++) {

            if (

                is_array($tokens[$i])
                && $tokens[$i][0] === T_CLASS

            ) {

                for ($j = $i + 1; $j < $count; $j++) {

                    if (

                        is_array($tokens[$j])
                        && $tokens[$j][0] === T_STRING

                    ) {

                        $classes[] =
                            $tokens[$j][1];

                        break;

                    }

                }

            }

        }

        return $classes;

    }

    /**
     * @return array<int,string>
     */
    public static function interfaces(
        string $contents
    ): array {

        $interfaces = [];
        $tokens = token_get_all($contents);
        $count = count($tokens);

        for ($i = 0; $i < $count; $i++) {

            if (

                is_array($tokens[$i])
                && $tokens[$i][0] === T_INTERFACE

            ) {

                for ($j = $i + 1; $j < $count; $j++) {

                    if (

                        is_array($tokens[$j])
                        && $tokens[$j][0] === T_STRING

                    ) {

                        $interfaces[] =
                            $tokens[$j][1];

                        break;

                    }

                }

            }

        }

        return $interfaces;

    }

    /**
     * @return array<int,string>
     */
    public static function traits(
        string $contents
    ): array {

        $traits = [];
        $tokens = token_get_all($contents);
        $count = count($tokens);

        for ($i = 0; $i < $count; $i++) {

            if (

                is_array($tokens[$i])
                && $tokens[$i][0] === T_TRAIT

            ) {

                for ($j = $i + 1; $j < $count; $j++) {

                    if (

                        is_array($tokens[$j])
                        && $tokens[$j][0] === T_STRING

                    ) {

                        $traits[] =
                            $tokens[$j][1];

                        break;

                    }

                }

            }

        }

        return $traits;

    }
}
