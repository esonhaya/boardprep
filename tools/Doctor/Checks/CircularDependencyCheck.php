<?php

declare(strict_types=1);

namespace Tools\Doctor\Checks;

use Tools\Doctor\Context\DoctorContext;
use Tools\Doctor\Contracts\CheckInterface;
use Tools\Doctor\DTO\CheckResult;

final class CircularDependencyCheck implements CheckInterface
{
    public function run(): CheckResult
    {
        $graph =
            DoctorContext::snapshot()
                ->metric("knowledge-graph");

        $cycles = [];

        foreach ($graph as $file => $node) {

            foreach (
                $node["depends_on"] ?? []
                as $dependency
            ) {

                if (
                    !isset($graph[$dependency])
                ) {
                    continue;
                }

                if (
                    in_array(
                        $file,
                        $graph[$dependency]["depends_on"] ?? [],
                        true
                    )
                ) {

                    $pair = [$file, $dependency];
                    sort($pair);

                    $cycles[
                        implode("|", $pair)
                    ] = $pair;

                }

            }

        }

        $details = array_map(
            fn(array $pair) =>
                basename($pair[0])
                . " ↔ "
                . basename($pair[1]),
            array_values($cycles)
        );

        return new CheckResult(
            title: "Circular Dependencies",
            status:
                $details === []
                    ? "PASS"
                    : "WARNING",
            summary:
                $details === []
                    ? "No circular dependencies detected."
                    : count($details)
                        . " circular dependencies detected.",
            details: $details
        );
    }

    public function category(): string
    {
        return "Architecture";
    }

    public function priority(): int
    {
        return 19;
    }
}
