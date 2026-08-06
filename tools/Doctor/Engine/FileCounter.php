<?php

declare(strict_types=1);

namespace Tools\Doctor\Engine;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final class FileCounter
{
    public static function phpFiles(string $directory): int
    {
        if (!is_dir($directory)) {
            return 0;
        }

        $count = 0;

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $directory,
                RecursiveDirectoryIterator::SKIP_DOTS
            )
        );

        foreach ($iterator as $file) {
            if (
                $file->isFile()
                && $file->getExtension() === "php"
            ) {
                $count++;
            }
        }

        return $count;
    }
}
