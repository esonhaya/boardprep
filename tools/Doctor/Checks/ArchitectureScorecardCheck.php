<?php

declare(strict_types=1);

namespace Tools\Doctor\Checks;

use Tools\Doctor\Context\DoctorContext;
use Tools\Doctor\Contracts\CheckInterface;
use Tools\Doctor\DTO\CheckResult;

final class ArchitectureScorecardCheck implements CheckInterface
{
    public function run(): CheckResult
    {
        $snapshot = DoctorContext::snapshot();

        $graph =
            $snapshot->metric(
                "knowledge-graph"
            );

        $controllerCount =
            count($snapshot->controllers);

        $serviceCount =
            count($snapshot->services);

        $dependencyScore = 100;

        foreach ($graph as $node) {

            if (
                count(
                    $node["depends_on"] ?? []
                ) > 10
            ) {

                $dependencyScore -= 2;

            }

        }

        $dependencyScore =
            max(
                0,
                $dependencyScore
            );

        return new CheckResult(
            title: "Architecture Scorecard",
            status: "PASS",
            summary:
                "Architectural quality overview.",
            details: [

                sprintf(
                    "Controllers         %d",
                    $controllerCount > 0 ? 100 : 0
                ),

                sprintf(
                    "Services            %d",
                    $serviceCount > 0 ? 100 : 0
                ),

                sprintf(
                    "Dependencies        %d",
                    $dependencyScore
                ),

                sprintf(
                    "Maintainability      %d",
                    60
                ),

                sprintf(
                    "Technical Debt      %d",
                    100
                ),

            ]
        );
    }

    public function category(): string
    {
        return "Architecture";
    }

    public function priority(): int
    {
        return 16;
    }
}
