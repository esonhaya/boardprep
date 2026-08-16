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

            $path = str_replace(
                DIRECTORY_SEPARATOR,
                "/",
                $file->getPathname()
            );

            /*
             * Doctor backups are generated artifacts, not project source.
             *
             * They intentionally contain historical PHP snapshots and must
             * not participate in source metrics, class maps, dependency
             * analysis, or contract checks.
             */
            if (
                str_contains(
                    $path,
                    "/storage/doctor-backups/"
                )
            ) {
                continue;
            }

            $files[] = $file->getPathname();

        }

        sort($files);

        return $files;

    }
}
