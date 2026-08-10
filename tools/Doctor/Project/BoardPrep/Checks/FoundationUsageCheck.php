<?php

declare(strict_types=1);

namespace Tools\Doctor\Project\BoardPrep\Checks;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tools\Doctor\Contracts\CheckInterface;
use Tools\Doctor\DTO\CheckResult;

final class FoundationUsageCheck implements CheckInterface
{
    public function run(): CheckResult
    {
        $classes = [
            "Arr",
            "Collection",
            "Html",
            "Str",
        ];

        $details = [];

        foreach ($classes as $class) {

            $count = 0;

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator(
                    "app",
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

                $contents = file_get_contents($file->getPathname());

                if ($contents !== false) {
                    $count += substr_count($contents, $class . "::");
                }
            }

            $details[] = sprintf("%-10s : %d", $class, $count);
        }

        return new CheckResult(
            title: "Foundation Usage",
            status: "PASS",
            summary: "Foundation adoption overview",
            details: $details
        );
    }

    public function category(): string
    {
        return "Foundation";
    }

    public function priority(): int
    {
        return 70;
    }
}
