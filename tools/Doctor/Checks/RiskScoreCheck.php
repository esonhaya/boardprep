<?php

declare(strict_types=1);

namespace Tools\Doctor\Checks;

use Tools\Doctor\Analyzers\DependencyRiskAnalyzer;
use Tools\Doctor\Context\DoctorContext;
use Tools\Doctor\Contracts\CheckInterface;
use Tools\Doctor\DTO\CheckResult;

final class RiskScoreCheck implements CheckInterface
{
    public function run(): CheckResult
    {
        $stats =
            DoctorContext::snapshot()
                ->metric('graph-statistics');

        if (!is_array($stats)) {
            return new CheckResult(
                title: 'Risk Score',
                status: 'WARNING',
                summary: 'Dependency risk data is unavailable.',
                recommendations: [
                    'Run the dependency analysis before calculating architecture risk.',
                ],
                score: 50,
            );
        }

        $analysis =
            (new DependencyRiskAnalyzer())
                ->analyze($stats);

        $risk = $analysis['risk'];

        $status =
            match (true) {
                $risk >= 70 => 'WARNING',
                $risk >= 40 => 'WARNING',
                default => 'PASS',
            };

        $level =
            match (true) {
                $risk >= 70 => 'High',
                $risk >= 40 => 'Moderate',
                default => 'Low',
            };

        return new CheckResult(
            title: 'Risk Score',
            status: $status,
            summary: "Risk Score: {$risk}/100 ({$level})",
            details: [
                'Highest coupling: '
                    . $analysis['highest_file']
                    . ' ('
                    . $analysis['highest_dependencies']
                    . ' dependencies)',
                'Total application dependencies: '
                    . $analysis['total_dependencies'],
                'Highly coupled files: '
                    . $analysis['highly_coupled'],
            ],
            recommendations:
                $risk >= 70
                    ? [
                        'Prioritize dependency concentration before major refactoring.',
                        'Review the highest-coupled application components first.',
                    ]
                    : (
                        $risk >= 40
                            ? [
                                'Monitor dependency concentration as the project grows.',
                            ]
                            : []
                    ),
            score: max(0, 100 - $risk),
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
