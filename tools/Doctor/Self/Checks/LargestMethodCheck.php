<?php

declare(strict_types=1);

namespace Tools\Doctor\Self\Checks;

use Tools\Doctor\Context\DoctorSelfContext;
use Tools\Doctor\Contracts\CheckInterface;
use Tools\Doctor\Diagnostics\DiagnosticFindingFactory;
use Tools\Doctor\DTO\CheckResult;
use Tools\Doctor\Rules\Rules;

final class LargestMethodCheck
    implements CheckInterface
{
    public function run(): CheckResult
    {
        $snapshot =
            DoctorSelfContext::snapshot();

        $methods =
            $snapshot->metric(
                "largest-method"
            );

        $largest =
            $methods[0] ?? null;

        if ($largest === null) {
            return new CheckResult(
                title: "Doctor Largest Method",
                status: "PASS",
                summary: sprintf(
                    "No Doctor methods exceed the recommended %d-line limit.",
                    Rules::methodMaxLines()
                ),
                score: 100,
            );
        }

        $lines =
            (int) (
                $largest["lines"] ?? 0
            );

        $threshold =
            Rules::methodMaxLines();

        $result = new CheckResult(
            title: "Doctor Largest Method",
            status: "WARNING",
            summary:
                "Doctor largest method contains {$lines} lines.",
            details: [
                "Method: "
                    . ($largest["name"] ?? "Unknown"),
                "File: "
                    . ($largest["file"] ?? "Unknown"),
            ],
            score: 70,
        );

        $result->addFinding(
            DiagnosticFindingFactory::warning(
                id: "doctor.method.large",
                rule: "doctor_largest_method",
                title: "Large Doctor Method",
                message: sprintf(
                    "%s contains %d lines, exceeding the recommended limit of %d.",
                    $largest["name"]
                        ?? "Unknown method",
                    $lines,
                    $threshold
                ),
                file:
                    $largest["file"] ?? null,
                symbol:
                    $largest["name"] ?? null,
                recommendation:
                    "Extract cohesive Doctor logic into smaller private methods or collaborators.",
                evidence: [
                    "lines" => $lines,
                    "threshold" => $threshold,
                ],
            )
        );

        return $result;
    }

    public function category(): string
    {
        return "doctor";
    }

    public function priority(): int
    {
        return 20;
    }
}
