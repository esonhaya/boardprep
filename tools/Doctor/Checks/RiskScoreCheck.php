<?php

declare(strict_types=1);

namespace Tools\Doctor\Checks;

use Tools\Doctor\Context\DoctorContext;
use Tools\Doctor\Contracts\CheckInterface;
use Tools\Doctor\DTO\CheckResult;

final class RiskScoreCheck implements CheckInterface
{
    public function run(): CheckResult
    {
        $score = 0;

        foreach (
            DoctorContext::snapshot()->metric('graph-statistics')
            as $row
        ) {
            $score += $row['total'] ?? 0;
        }

        return new CheckResult(
            title: 'Risk Score',
            status: $score >= 800
                ? 'WARNING'
                : 'PASS',
            summary: "Risk Score: {$score}"
        );
    }

    public function category(): string
    {
        return 'Architecture';
    }

    public function priority(): int
    {
        return 21;
    }
}
