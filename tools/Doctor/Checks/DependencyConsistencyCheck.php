<?php

declare(strict_types=1);

namespace Tools\Doctor\Checks;

use Tools\Doctor\Context\DoctorContext;
use Tools\Doctor\Contracts\CheckInterface;
use Tools\Doctor\DTO\CheckResult;
use Tools\Doctor\Scanners\DependencyConsistency\DependencyConsistencyPolicy;
use Tools\Doctor\Scanners\DependencyConsistencyScanner;

final class DependencyConsistencyCheck implements CheckInterface
{
    public function run(): CheckResult
    {
        $policy = new DependencyConsistencyPolicy();

        $files = array_values(array_filter(
            DoctorContext::snapshot()->files,
            static function (array $file) use ($policy): bool {
                return $policy->includes(
                    (string) ($file['path'] ?? '')
                );
            }
        ));

        $result = DependencyConsistencyScanner::scan(
            $files,
            $policy
        );

        $issues = $result['issues'];

        if ($issues === []) {
            return new CheckResult(
                title: 'Dependency Consistency',
                status: 'PASS',
                summary: sprintf(
                    'No cross-file API inconsistencies detected across %d application PHP file(s).',
                    count($files)
                ),
                details: [
                    'Class resolution ........ PASS',
                    'Method contracts ........ PASS',
                    'Property contracts ...... PASS',
                    'Constructor arguments ... PASS',
                    'Inheritance contracts ... PASS',
                ],
                recommendations: []
            );
        }

        $details = [];

        foreach (
            array_slice($issues, 0, $policy->maxIssues)
            as $issue
        ) {
            $details[] = sprintf(
                '%s:%d  %s',
                $issue['file'],
                (int) $issue['line'],
                $issue['message']
            );
        }

        if (count($issues) > $policy->maxIssues) {
            $details[] = sprintf(
                '... %d additional issue(s) omitted from console output.',
                count($issues) - $policy->maxIssues
            );
        }

        return new CheckResult(
            title: 'Dependency Consistency',
            status: 'FAIL',
            summary: sprintf(
                '%d cross-file API consistency issue(s) detected.',
                count($issues)
            ),
            details: $details,
            recommendations: [
                'Fix dependency API mismatches before relying on application simulations.',
                'Treat typed properties, method signatures, constructors, and inheritance contracts as cross-file APIs.',
            ],
            score: 0
        );
    }

    public function category(): string
    {
        return 'Architecture';
    }

    public function priority(): int
    {
        return 12;
    }
}
