<?php

declare(strict_types=1);

namespace Tools\Doctor\Project\BoardPrep\Checks;

use Tools\Doctor\Context\DoctorContext;
use Tools\Doctor\Contracts\CheckInterface;
use Tools\Doctor\DTO\CheckResult;
use Tools\Doctor\Rules\Rules;

final class QualityGateCheck implements CheckInterface
{
    public function run(): CheckResult
    {
        $snapshot = DoctorContext::snapshot();

        $warnings = 0;

        if (
            count($snapshot->metric('layer-violations')) > 0
        ) {
            $warnings++;
        }

        if (
            count($snapshot->metric('largest-method')) > 0
        ) {
            $warnings++;
        }

        $largestService =
            $snapshot->largestFile('/Services/');

        if (
            ($largestService['lines'] ?? 0)
            > Rules::serviceMaxLines()
        ) {
            $warnings++;
        }

        return new CheckResult(
            title: 'Quality Gate',
            status: $warnings === 0 ? 'PASS' : 'WARNING',
            summary: $warnings === 0
                ? 'All quality gates passed.'
                : "{$warnings} quality gate(s) require attention."
        );
    }

    public function category(): string
    {
        return 'Architecture';
    }

    public function priority(): int
    {
        return 19;
    }
}
