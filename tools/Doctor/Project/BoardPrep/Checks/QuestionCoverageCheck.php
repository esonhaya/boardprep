<?php

declare(strict_types=1);

namespace Tools\Doctor\Project\BoardPrep\Checks;

use App\Services\Shared\QuestionCoverageService;
use Tools\Doctor\Contracts\CheckInterface;
use Tools\Doctor\DTO\CheckResult;

final class QuestionCoverageCheck implements CheckInterface
{
    public function run(): CheckResult
    {
        $report = QuestionCoverageService::analyzeRepository();
        $inventory = $report['inventory'];
        $issues = $report['issues'];
        $shortages = 0;
        $details = [
            sprintf(
                'Questions: %d total, %d production eligible.',
                $inventory['total'],
                $inventory['eligible']
            ),
            'Difficulty: ' . json_encode($inventory['by_difficulty'], JSON_UNESCAPED_UNICODE),
            'Subjects: ' . json_encode($inventory['by_subject'], JSON_UNESCAPED_UNICODE),
        ];
        foreach ($report['blueprints'] as $blueprint) {
            foreach ($blueprint['categories'] as $category) {
                if ($category['shortage_per_100'] > 0) {
                    $shortages++;
                }
                $details[] = sprintf(
                    '%s/%s: requires %d per 100; %d eligible; shortage %d.',
                    $blueprint['board'],
                    $category['subject'],
                    $category['required_per_100'],
                    $category['available'],
                    $category['shortage_per_100']
                );
            }
        }
        $structural = count($issues['unknown_taxonomy'])
            + count($issues['invalid_difficulty'])
            + count($issues['ineligible'])
            + count($issues['taxonomy_orphans']);
        $warnings = $structural + $shortages;

        return new CheckResult(
            title: 'Question Coverage',
            status: $warnings === 0 ? 'PASS' : 'WARNING',
            summary: $warnings === 0
                ? 'Question coverage and configured blueprints are feasible.'
                : sprintf('%d structural/eligibility issue(s) and %d blueprint shortage(s).', $structural, $shortages),
            details: $details,
            recommendations: $warnings === 0 ? [] : [
                'Repair structural metadata defects; treat remaining zero/sparse allocations as content shortages.'
            ]
        );
    }

    public function category(): string
    {
        return 'Content';
    }

    public function priority(): int
    {
        return 25;
    }
}
