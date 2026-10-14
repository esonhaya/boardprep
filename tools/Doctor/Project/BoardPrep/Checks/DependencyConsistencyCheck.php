<?php

declare(strict_types=1);

namespace Tools\Doctor\Project\BoardPrep\Checks;

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
        $unresolved = [];
        $classified = ['FIXED_ANALYZER' => 0, 'JUSTIFIED_DYNAMIC' => 0];
        $examples = ['FIXED_ANALYZER' => [], 'JUSTIFIED_DYNAMIC' => []];
        foreach ($issues as $issue) {
            $classification = DependencyFindingClassifier::classify($issue, $result['symbols']);
            if ($classification === null) {
                $unresolved[] = $issue;
                continue;
            }
            $kind = $classification['classification'];
            $classified[$kind] = ($classified[$kind] ?? 0) + 1;
            if (count($examples[$kind] ?? []) < 2) {
                $examples[$kind][] = sprintf(
                    '%s:%d %s — %s',
                    $issue['file'],
                    (int) $issue['line'],
                    $issue['message'],
                    $classification['evidence']
                );
            }
        }

        if ($unresolved === []) {
            $details = [
                'Raw analyzer findings: ' . count($issues),
                'FIXED_ANALYZER: ' . $classified['FIXED_ANALYZER'],
                'JUSTIFIED_DYNAMIC: ' . $classified['JUSTIFIED_DYNAMIC'],
                'UNRESOLVED: 0',
            ];
            foreach (array_merge($examples['FIXED_ANALYZER'], $examples['JUSTIFIED_DYNAMIC']) as $example) {
                $details[] = $example;
            }
            return new CheckResult(
                title: 'Dependency Consistency',
                status: 'PASS',
                summary: sprintf(
                    'No unresolved cross-file API inconsistencies across %d application PHP file(s); %d analyzer artifact(s) and %d justified dynamic call(s) classified.',
                    count($files),
                    $classified['FIXED_ANALYZER'],
                    $classified['JUSTIFIED_DYNAMIC']
                ),
                details: $details,
                recommendations: []
            );
        }

        $details = [];

        foreach (
            array_slice($unresolved, 0, $policy->maxIssues)
            as $issue
        ) {
            $details[] = sprintf(
                '%s:%d  %s',
                $issue['file'],
                (int) $issue['line'],
                $issue['message']
            );
        }

        if (count($unresolved) > $policy->maxIssues) {
            $details[] = sprintf(
                '... %d additional issue(s) omitted from console output.',
                count($unresolved) - $policy->maxIssues
            );
        }
        $details[] = 'CLASSIFIED FIXED_ANALYZER=' . $classified['FIXED_ANALYZER']
            . ' JUSTIFIED_DYNAMIC=' . $classified['JUSTIFIED_DYNAMIC'];

        return new CheckResult(
            title: 'Dependency Consistency',
            status: 'FAIL',
            summary: sprintf(
                '%d unresolved cross-file API consistency issue(s) detected; %d analyzer artifact(s) classified.',
                count($unresolved),
                $classified['FIXED_ANALYZER']
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
