<?php

declare(strict_types=1);

namespace Tools\Doctor\Project\BoardPrep\Checks;

use App\Services\RepositoryHealth\Engine\RepositoryHealthEngine;
use Tools\Doctor\Contracts\CheckInterface;
use Tools\Doctor\DTO\CheckResult;

final class ContentIntegrityCheck implements CheckInterface
{
    public function run(): CheckResult
    {
        $health = RepositoryHealthEngine::analyze();
        $summary = $health->summary;
        $statistics = $health->statistics->toArray();
        $portfolio = $health->summary['flashcards'] ?? [];
        $status = ($summary['fail_count'] ?? 0) > 0 ? 'FAIL' : 'PASS';
        $details = [
            sprintf(
                'Repository Health: %d questions, %d eligible, %d derivable cards, %d justified exclusions, %d defects, status %s.',
                $statistics['total_questions'] ?? 0,
                $portfolio['eligible'] ?? 0,
                $portfolio['derivable'] ?? 0,
                $portfolio['justified_exclusion'] ?? 0,
                $portfolio['nonderivable_defect'] ?? 0,
                $health->overallStatus
            ),
        ];
        foreach ($health->boardSummaries as $board) {
            $details[] = sprintf(
                'BOARD=%s QUESTIONS=%d EFFECTIVE=%d RUNTIME=%d FLASHCARDS=%d/%d STATUS=%s',
                $board['board_id'],
                $board['canonical'] ?? 0,
                $board['effective_declared'] ?? 0,
                $board['runtime'] ?? 0,
                $board['flashcards']['derivable'] ?? 0,
                $board['flashcards']['eligible'] ?? 0,
                $board['zero'] === 0 ? 'PASS' : 'WARN'
            );
        }
        $defectCount = (int) ($summary['fail_count'] ?? 0);

        return new CheckResult(
            title: 'Content Integrity',
            status: $status,
            summary: $status === 'PASS'
                ? 'Structural content integrity and portfolio readiness checks passed.'
                : sprintf('%d content integrity defect(s) require repair.', $defectCount),
            details: $details,
            recommendations: $status === 'PASS' ? [] : [
                'Repair the exact identifiers listed by the content integrity report before release.'
            ],
            score: $status === 'PASS' ? 100 : 0,
        );
    }

    public function category(): string
    {
        return 'Content';
    }

    public function priority(): int
    {
        return 24;
    }
}
