<?php

declare(strict_types=1);

namespace Tools\Doctor\Scanners;

final class DependencyScanner
{
    /**
     * @return array<int,string>
     */
    public static function classes(
        string $contents
    ): array
    {
        $dependencies = [];

        $patterns = [

            '/new\s+([A-Z][A-Za-z0-9_\\\\]*)/',

            '/([A-Z][A-Za-z0-9_]+)::/',

            '/extends\s+([A-Z][A-Za-z0-9_\\\\]*)/',

            '/implements\s+([A-Z][A-Za-z0-9_\\\\,\s]+)/',

            '/\(\s*([A-Z][A-Za-z0-9_\\\\]*)\s+\$/',

            '/,\s*([A-Z][A-Za-z0-9_\\\\]*)\s+\$/',

            '/(?:public|protected|private)\s+([A-Z][A-Za-z0-9_\\\\]*)\s+\$/',

        ];

        foreach ($patterns as $pattern) {

            preg_match_all(
                $pattern,
                $contents,
                $matches
            );

            foreach ($matches[1] ?? [] as $match) {

                if (str_contains($match, ",")) {

                    foreach (explode(",", $match) as $item) {

                        $dependencies[] =
                            trim($item);

                    }

                } else {

                    $dependencies[] =
                        trim($match);

                }

            }

        }

        return array_values(
            array_unique(
                array_filter($dependencies)
            )
        );
    }
}
