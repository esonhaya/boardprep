<?php

declare(strict_types=1);

namespace Tools\Doctor\Project\BoardPrep\Checks;

use Tools\Doctor\Contracts\CheckInterface;
use Tools\Doctor\Context\DoctorContext;
use Tools\Doctor\Diagnostics\DiagnosticFindingFactory;
use Tools\Doctor\Rules\Rules;
use Tools\Doctor\DTO\CheckResult;

final class LargestMethodCheck implements CheckInterface
{
    public function run(): CheckResult
    {
        $snapshot = DoctorContext::snapshot();

        $largeMethods =
            $snapshot->metric('largest-method');

        $largest =
            $largeMethods[0] ?? null;

        if ($largest === null) {
            return new CheckResult(
                title: 'Largest Method',
                status: 'PASS',
                summary: sprintf(
                    'No methods exceed the recommended %d-line limit.',
                    Rules::methodMaxLines()
                ),
                score: 100,
            );
        }

        $lines =
            (int) ($largest['lines'] ?? 0);

        $threshold = Rules::methodMaxLines();

        $result = new CheckResult(
            title: 'Largest Method',
            status: 'WARNING',
            summary: "Largest method contains {$lines} lines.",
            details: [
                'Method: ' . ($largest['name'] ?? 'Unknown'),
                'File: ' . ($largest['file'] ?? 'Unknown'),
            ],
            score: 70,
        );

        $result->addFinding(
            DiagnosticFindingFactory::warning(
                id: 'method.large',
                rule: 'largest_method',
                title: 'Large Method',
                message: sprintf(
                    '%s contains %d lines, exceeding the recommended limit of %d.',
                    $largest['name'] ?? 'Unknown method',
                    $lines,
                    $threshold
                ),
                file: $largest['file'] ?? null,
                symbol: $largest['name'] ?? null,
                recommendation:
                    'Extract cohesive logic into smaller private methods or collaborators.',
                evidence: [
                    'lines' => $lines,
                    'threshold' => $threshold,
                ],
            )
        );

        return $result;
    }

    public function category(): string
    {
        return 'complexity';
    }

    public function priority(): int
    {
        return 20;
    }
}
