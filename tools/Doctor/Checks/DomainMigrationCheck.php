<?php

declare(strict_types=1);

namespace Tools\Doctor\Checks;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tools\Doctor\Contracts\CheckInterface;
use Tools\Doctor\DTO\CheckResult;

final class DomainMigrationCheck implements CheckInterface
{
    public function run(): CheckResult
    {
        $implemented = 0;
        $empty = 0;

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                "app/Domains",
                RecursiveDirectoryIterator::SKIP_DOTS
            ),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {

            if (!$item->isDir()) {
                continue;
            }

            $children = scandir($item->getPathname());

            if ($children === false) {
                continue;
            }

            if (count($children) === 2) {
                $empty++;
            } else {
                $implemented++;
            }
        }

        return new CheckResult(
            title: "Domain Migration",
            status: $empty === 0 ? "PASS" : "WARNING",
            summary: "{$implemented} implemented, {$empty} empty",
            details: [
                "Implemented : {$implemented}",
                "Empty       : {$empty}",
            ],
            recommendations: $empty === 0
                ? []
                : ["Continue implementing the Domain layer as features are added."]
        );
    }

    public function category(): string
    {
        return "Architecture";
    }

    public function priority(): int
    {
        return 40;
    }
}
