<?php

declare(strict_types=1);

namespace Tools\Doctor\Project\BoardPrep\Checks;

use Tools\Doctor\Context\DoctorContext;
use Tools\Doctor\Contracts\CheckInterface;
use Tools\Doctor\DTO\CheckResult;

final class DeadClassCheck implements CheckInterface
{
    public function run(): CheckResult
    {
        $snapshot = DoctorContext::snapshot();

        $graph =
            $snapshot->metric("knowledge-graph");

        $dead = [];

        foreach ($snapshot->classMap as $class => $file) {

            if (str_contains($file, "/tools/Doctor/")) {
                continue;
            }

            $usedBy =
                $graph[$file]["used_by"] ?? [];

            if ($usedBy !== []) {
                continue;
            }

            $dead[] = [
                "class" => $class,
                "file" => $file,
            ];

        }

        usort(
            $dead,
            fn($a, $b) =>
                strcmp($a["class"], $b["class"])
        );

        return new CheckResult(
            title: "Dead Classes",
            status: $dead === []
                ? "PASS"
                : "WARNING",
            summary: sprintf(
                "%d potentially unused classes.",
                count($dead)
            ),
            details: array_map(
                fn($item) =>
                    "{$item["class"]} ({$item["file"]})",
                array_slice($dead, 0, 15)
            )
        );
    }

    public function category(): string
    {
        return "Architecture";
    }

    public function priority(): int
    {
        return 18;
    }
}
