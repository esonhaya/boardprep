<?php

declare(strict_types=1);

namespace Tools\Doctor\Project\BoardPrep\Checks;

use Tools\Doctor\Contracts\CheckInterface;
use Tools\Doctor\Context\DoctorContext;
use Tools\Doctor\DTO\CheckResult;

final class TechnicalDebtCheck implements CheckInterface
{
    public function run(): CheckResult
    {
        $snapshot = DoctorContext::snapshot();

        $debt = 0;

        foreach ($snapshot->files as $file) {

            if (($file["lines"] ?? 0) > 300) {
                $debt += 5;
            }

        }

        $debt += count($snapshot->metric("layer-violations")) * 3;
        $debt += count($snapshot->metric("dead-classes"));
        $debt += count($snapshot->metric("unused-imports"));

        return new CheckResult(
            title: "Technical Debt",
            status: $debt > 50 ? "WARNING" : "PASS",
            summary: "Debt Score: {$debt}",
            recommendations: $debt > 50
                ? [
                    "Reduce long files.",
                    "Resolve architectural violations.",
                    "Remove dead code."
                ]
                : []
        );
    }

    public function category(): string
    {
        return "Architecture";
    }

    public function priority(): int
    {
        return 17;
    }
}
