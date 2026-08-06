<?php

declare(strict_types=1);

namespace Tools\Doctor\Scanners;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final class FileScanner
{
    /**
     * @return string[]
     */
    public static function php(
        string $root = "."
    ): array {

        $files = [];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $root,
                RecursiveDirectoryIterator::SKIP_DOTS
            )
        );

        foreach ($iterator as $file) {

            if (
                !$file->isFile()
                || $file->getExtension() !== "php"
            ) {
                continue;
            }

            $files[] = $file->getPathname();

        }

        sort($files);

        return $files;

    }
}
