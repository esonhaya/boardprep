<?php

declare(strict_types=1);

namespace Tools\Doctor\Checks;

use Tools\Doctor\Advisor\FixRecommendationAdvisor;
use Tools\Doctor\Context\DoctorContext;
use Tools\Doctor\Contracts\CheckInterface;
use Tools\Doctor\DTO\CheckResult;

final class LayerViolationCheck implements CheckInterface
{
    public function run(): CheckResult
    {
        $snapshot = DoctorContext::snapshot();

        $violations = [];

        foreach ($snapshot->dependencies as $file => $dependencies) {

            foreach ($dependencies as $dependency) {

                $target = $snapshot->classMap[$dependency] ?? null;

                if ($target === null) {
                    continue;
                }

                if (
                    str_contains($file, "/Repositories/")
                    && str_contains($target, "/Controllers/")
                ) {

                    $violations[] =
                        basename($file)
                        . " -> "
                        . basename($target);

                }

                if (
                    str_contains($file, "/Controllers/")
                    && str_contains($target, "/Repositories/")
                ) {

                    $violations[] =
                        basename($file)
                        . " -> "
                        . basename($target);

                }

            }

        }

        $violations = array_values(array_unique($violations));

        return new CheckResult(

            title: "Layer Violations",

            status:
                empty($violations)
                    ? "PASS"
                    : "WARNING",

            summary:
                empty($violations)
                    ? "No layer violations detected."
                    : count($violations) . " layer violations detected.",

            details:
                array_slice(
                    $violations,
                    0,
                    15
                ),

            recommendations:
                FixRecommendationAdvisor::for(
                    "Layer Violations"
                )

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
