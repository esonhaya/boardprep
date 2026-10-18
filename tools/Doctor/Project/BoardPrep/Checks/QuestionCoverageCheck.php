<?php

declare(strict_types=1);

namespace Tools\Doctor\Project\BoardPrep\Checks;

use App\Services\RepositoryHealth\Engine\RepositoryHealthEngine;
use Tools\Doctor\Contracts\CheckInterface;
use Tools\Doctor\DTO\CheckResult;

final class QuestionCoverageCheck implements CheckInterface
{
    public function run(): CheckResult
    {
        // ContentIntegrityCheck runs immediately before this check and
        // publishes the same canonical report. The fallback keeps this check
        // usable on its own without reintroducing the historical quadratic
        // coverage scan.
        $health = RepositoryHealthEngine::lastReport() ?? RepositoryHealthEngine::analyze();
        $statistics = $health->statistics->toArray();
        $warnings = 0;
        $depth = (array) ($statistics['coverage']['subject_depth'] ?? []);
        $belowTarget = array_values(array_filter((array) ($depth['below_target'] ?? []), 'is_array'));
        $details = [
            sprintf(
                'Questions: %d total; production eligible counts are exposed per board.',
                $statistics['totals']['canonical'] ?? $statistics['total_questions'] ?? 0,
            ),
            sprintf(
                'Repository totals: canonical %d; effective %d; runtime %d.',
                $statistics['totals']['canonical'] ?? $statistics['total_questions'] ?? 0,
                $statistics['totals']['effective_declared'] ?? 0,
                $statistics['totals']['runtime'] ?? 0
            ),
            sprintf(
                'Effective board-subject depth: %d active selectors; %d below the %d-question planning target.',
                (int) ($depth['active_board_subjects'] ?? 0),
                count($belowTarget),
                (int) ($depth['target'] ?? 100)
            ),
        ];
        $warnings += count($belowTarget);
        foreach ($belowTarget as $scope) {
            $details[] = sprintf(
                'SUBJECT_DEPTH_BELOW_100 %s/%s: %d effective unique questions; deficit %d.',
                (string) ($scope['board_id'] ?? ''),
                (string) ($scope['subject_id'] ?? ''),
                (int) ($scope['effective_eligible_question_count'] ?? 0),
                (int) ($scope['deficit_to_target'] ?? 0)
            );
        }
        foreach (array_slice((array) ($depth['review_candidates'] ?? []), 0, 10) as $review) {
            if (!is_array($review)) continue;
            $details[] = sprintf(
                '%s %s/%s: %d concepts across %d topics; heuristic review only.',
                (string) ($review['review_type'] ?? 'SUBJECT_COVERAGE_REVIEW'),
                (string) ($review['board_id'] ?? ''),
                (string) ($review['subject_id'] ?? ''),
                (int) ($review['unique_concept_count'] ?? 0),
                (int) ($review['unique_topic_count'] ?? 0)
            );
        }
        foreach ($health->boardSummaries as $boardId => $board) {
            $thin = (int) ($board['under20'] ?? 0);
            $warnings += $thin + (int) ($board['zero'] ?? 0);
            $details[] = sprintf(
                '%s: canonical %d, effective %d, runtime %d; active leaves %d; under20 %d; zero %d.',
                $boardId,
                $board['canonical'] ?? 0,
                $board['effective_declared'] ?? 0,
                $board['runtime'] ?? 0,
                $board['active_leaves'] ?? 0,
                $thin,
                $board['zero'] ?? 0
            );
        }
        $failures = (int) ($health->summary['fail_count'] ?? 0);
        $reviews = (int) ($health->summary['review_count'] ?? 0);
        $status = $failures > 0 ? 'FAIL' : ($warnings > 0 || $reviews > 0 ? 'WARNING' : 'PASS');

        return new CheckResult(
            title: 'Question Coverage',
            status: $status,
            summary: sprintf(
                'Canonical Repository Health reports %d deterministic failures, %d review items, and %d thin/zero-leaf signals.',
                $failures,
                $reviews,
                $warnings
            ),
            details: array_slice($details, 0, 30),
            recommendations: $status === 'PASS' ? [] : [
                'Use the Repository Health action queue for deterministic repairs and future content planning.'
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
