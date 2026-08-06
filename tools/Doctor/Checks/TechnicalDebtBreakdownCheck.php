<?php

declare(strict_types=1);

namespace Tools\Doctor\Checks;

use Tools\Doctor\Contracts\CheckInterface;
use Tools\Doctor\Context\DoctorContext;
use Tools\Doctor\DTO\CheckResult;

final class TechnicalDebtBreakdownCheck implements CheckInterface
{
    public function run(): CheckResult
    {
        $snapshot = DoctorContext::snapshot();

        $largestMethods =
            count($snapshot->metric("largest-method"));

        $largestServices =
            count($snapshot->metric("largest-service"));

        $layers =
            count($snapshot->metric("layer-violations"));

        $legacy =
            count($snapshot->metric("legacy-files"));

        $unused =
            count($snapshot->metric("unused-imports"));

        $dead =
            count($snapshot->metric("dead-classes"));

        $total =
            ($largestMethods * 10) +
            ($largestServices * 8) +
            ($layers * 5) +
            ($legacy * 2) +
            $unused +
            $dead;

        return new CheckResult(
            title: "Technical Debt Breakdown",
            status: "PASS",
            summary: "Debt Score: {$total}",
            details: [
                "Largest Methods ...... " . ($largestMethods * 10),
                "Largest Services ..... " . ($largestServices * 8),
                "Layer Violations ..... " . ($layers * 5),
                "Legacy Files ......... " . ($legacy * 2),
                "Unused Imports ....... " . $unused,
                "Dead Classes ......... " . $dead,
            ]
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
