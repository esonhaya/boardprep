<?php

declare(strict_types=1);

namespace Tools\Doctor\Project\BoardPrep\Checks;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tools\Doctor\Contracts\CheckInterface;
use Tools\Doctor\DTO\CheckResult;

final class LegacyFileCheck implements CheckInterface
{
    public function run(): CheckResult
    {
        $legacy = [];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                ".",
                RecursiveDirectoryIterator::SKIP_DOTS
            )
        );

        foreach ($iterator as $file) {

            if (!$file->isFile()) {
                continue;
            }

            $path = str_replace(
                getcwd() . DIRECTORY_SEPARATOR,
                "",
                $file->getPathname()
            );

            if (str_starts_with($path, "storage/doctor/")) {
                continue;
            }

            if (
                str_ends_with($path, ".bak")
                || str_contains($path, ".old")
                || str_contains($path, ".legacy")
            ) {
                $legacy[] = "./" . $path;
            }
        }

        return new CheckResult(
            title: "Legacy Files",
            status: empty($legacy) ? "PASS" : "WARNING",
            summary: empty($legacy)
                ? "No legacy files found."
                : count($legacy) . " legacy files found.",
            details: array_slice($legacy, 0, 20),
            recommendations: empty($legacy)
                ? []
                : [
                    "Remove obsolete backup or legacy files from the project."
                ]
        );
    }

    public function category(): string
    {
        return "Cleanup";
    }

    public function priority(): int
    {
        return 50;
    }
}
