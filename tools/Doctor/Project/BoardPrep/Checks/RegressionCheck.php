<?php

declare(strict_types=1);

namespace Tools\Doctor\Project\BoardPrep\Checks;

use Tools\Doctor\Baseline\BaselineManager;
use Tools\Doctor\Context\DoctorContext;
use Tools\Doctor\Contracts\CheckInterface;
use Tools\Doctor\DTO\CheckResult;

final class RegressionCheck implements CheckInterface
{
    public function run(): CheckResult
    {
        $baseline =
            new BaselineManager();

        if (!$baseline->exists()) {

            return new CheckResult(
                title: "Regression Check",
                status: "PASS",
                summary: "No baseline available.",
                recommendations: [
                    "Capture a baseline to enable regression detection."
                ]
            );

        }

        $base =
            $baseline->baseline();

        $snapshot =
            DoctorContext::snapshot();

        $details = [];
        $warnings = 0;

        $currentPhp =
            $snapshot->phpFileCount();

        $basePhp =
            $base["phpFiles"] ?? $currentPhp;

        if ($currentPhp > $basePhp) {

            $details[] =
                sprintf(
                    "PHP files: %d → %d",
                    $basePhp,
                    $currentPhp
                );

        }

        $currentClasses =
            count($snapshot->classes);

        $baseClasses =
            $base["classes"] ?? $currentClasses;

        if ($currentClasses > $baseClasses) {

            $details[] =
                sprintf(
                    "Classes: %d → %d",
                    $baseClasses,
                    $currentClasses
                );

        }

        $largestController =
            $snapshot->largestFile("/Controllers/");

        $baseController =
            $base["largestController"]["lines"] ?? 0;

        if (
            ($largestController["lines"] ?? 0)
            > $baseController
        ) {

            $warnings++;

            $details[] =
                sprintf(
                    "Largest Controller: %d → %d",
                    $baseController,
                    $largestController["lines"]
                );

        }

        $largestService =
            $snapshot->largestFile("/Services/");

        $baseService =
            $base["largestService"]["lines"] ?? 0;

        if (
            ($largestService["lines"] ?? 0)
            > $baseService
        ) {

            $warnings++;

            $details[] =
                sprintf(
                    "Largest Service: %d → %d",
                    $baseService,
                    $largestService["lines"]
                );

        }

        return new CheckResult(
            title: "Regression Check",
            status:
                $warnings === 0
                    ? "PASS"
                    : "WARNING",
            summary:
                $warnings === 0
                    ? "No regressions detected."
                    : "{$warnings} regression(s) detected.",
            details: $details,
            recommendations:
                $warnings === 0
                    ? []
                    : [
                        "Avoid increasing file sizes beyond the established baseline."
                    ]
        );
    }

    public function category(): string
    {
        return "History";
    }

    public function priority(): int
    {
        return 15;
    }
}
