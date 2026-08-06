<?php

declare(strict_types=1);

namespace Tools\Doctor\Analyzers;

final class CyclomaticAnalyzer
{
    /**
     * @return array{
     *     complexity:int
     * }
     */
    public static function analyze(
        string $contents,
        int $startLine,
        int $endLine
    ): array {

        $lines = explode(
            "\n",
            $contents
        );

        $segment = implode(
            "\n",
            array_slice(
                $lines,
                $startLine - 1,
                $endLine - $startLine + 1
            )
        );

        $complexity = 1;

        $patterns = [

            '/\bif\b/',
            '/\belseif\b/',
            '/\bfor\b/',
            '/\bforeach\b/',
            '/\bwhile\b/',
            '/\bcase\b/',
            '/\bcatch\b/',
            '/\bmatch\b/',
            '/&&/',
            '/\|\|/',
            '/\?/',

        ];

        foreach ($patterns as $pattern) {

            preg_match_all(
                $pattern,
                $segment,
                $matches
            );

            $complexity += count(
                $matches[0]
            );

        }

        return [

            "complexity" =>
                $complexity,

        ];

    }
}
