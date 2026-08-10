<?php

declare(strict_types=1);

namespace Tools\Doctor\Project\BoardPrep\Checks;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tools\Doctor\Contracts\CheckInterface;
use Tools\Doctor\DTO\CheckResult;

final class EmptyDirectoryCheck implements CheckInterface
{
    public function run(): CheckResult
    {
        $directories = [];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                "app",
                RecursiveDirectoryIterator::SKIP_DOTS
            ),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {

            if (!$item->isDir()) {
                continue;
            }

            $children = scandir($item->getPathname());

            if ($children !== false && count($children) === 2) {
                $directories[] = $item->getPathname();
            }
        }

        return new CheckResult(
            title: "Empty Directories",
            status: empty($directories) ? "PASS" : "WARNING",
            summary: empty($directories)
                ? "No empty directories found."
                : count($directories) . " empty directories found.",
            details: $directories,
            recommendations: empty($directories)
                ? []
                : ["Remove placeholders or implement the remaining domain folders."]
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
