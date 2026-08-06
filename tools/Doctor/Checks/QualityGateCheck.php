<?php

declare(strict_types=1);

namespace Tools\Doctor\Checks;

use Tools\Doctor\Contracts\CheckInterface;
use Tools\Doctor\Context\DoctorContext;
use Tools\Doctor\DTO\CheckResult;

final class QualityGateCheck implements CheckInterface
{
    public function run(): CheckResult
    {
        $snapshot = DoctorContext::snapshot();

        $warnings =
            count($snapshot->metric('layer-violations'))
            + count($snapshot->metric('largest-method'))
            + count($snapshot->metric('largest-service'));

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
