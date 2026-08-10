<?php

declare(strict_types=1);

namespace Tools\Doctor\Project\BoardPrep\Checks;

use Tools\Doctor\Contracts\CheckInterface;
use Tools\Doctor\Context\DoctorContext;
use Tools\Doctor\Diagnostics\DiagnosticFindingFactory;
use Tools\Doctor\DTO\CheckResult;

final class LargestMethodCheck implements CheckInterface
{
    public function run(): CheckResult
    {
        $snapshot = DoctorContext::snapshot();

        $largest = null;

        foreach ($snapshot->methods as $method) {
            if (
                $largest === null
                || ($method['lines'] ?? 0) > ($largest['lines'] ?? 0)
            ) {
                $largest = $method;
            }
        }

        if ($largest === null) {
            return new CheckResult(
                title: 'Largest Method',
                status: 'INFO',
                summary: 'No methods were found in the project.',
                score: 100,
            );
        }

        $lines = (int) ($largest['lines'] ?? 0);
        $threshold = 80;

        $result = new CheckResult(
            title: 'Largest Method',
            status: $lines > $threshold ? 'WARNING' : 'PASS',
            summary: "Largest method contains {$lines} lines.",
            details: [
                'Method: ' . ($largest['name'] ?? 'Unknown'),
                'File: ' . ($largest['file'] ?? 'Unknown'),
            ],
            score: $lines > $threshold ? 70 : 100,
        );

        if ($lines > $threshold) {
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
        }

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
