<?php

declare(strict_types=1);

namespace Tools\Doctor\Project\BoardPrep\Checks;

use App\Services\Shared\ContentIntegrityService;
use Tools\Doctor\Contracts\CheckInterface;
use Tools\Doctor\DTO\CheckResult;

final class ContentIntegrityCheck implements CheckInterface
{
    public function run(): CheckResult
    {
        $report = ContentIntegrityService::analyzeRepository();
        $portfolio = $report['portfolio'];
        $status = $portfolio['status'] === 'FAIL' ? 'FAIL' : 'PASS';
        $details = [
            sprintf(
                'Portfolio: %d questions, %d/%d eligible, %d linked, %d cards, %d justified exclusions, status %s.',
                $portfolio['total_canonical'],
                $portfolio['total_eligible'],
                $portfolio['total_canonical'],
                $portfolio['total_linked'],
                $portfolio['total_derivable_cards'],
                $portfolio['total_justified_exclusions'],
                $portfolio['status']
            ),
        ];
        foreach ($report['boards'] as $board) {
            $details[] = sprintf(
                'BOARD=%s QUESTIONS=%d ELIGIBLE=%d/%d LINKED=%d FLASHCARDS=%d JUSTIFIED_EXCLUSIONS=%d ACCIDENTAL_NON_DERIVABLE=%d INVALID_LINKS=%d BROKEN_ASSETS=%d STATUS=%s',
                $board['board_id'],
                $board['canonical_owned'],
                $board['owned_eligible'],
                $board['canonical_owned'],
                $board['linked_questions'],
                $board['derivable_flashcards'],
                $board['justified_flashcard_exclusions'],
                count($board['accidental_non_derivable']),
                count($board['invalid_study_links']),
                count($board['broken_structured_assets']),
                $board['readiness_status']
            );
        }
        if (($report['semantic_review']['standalone_study_materials'] ?? []) !== []) {
            $details[] = 'SEMANTIC_REVIEW=standalone study materials are surfaced for editorial review: '
                . implode(', ', $report['semantic_review']['standalone_study_materials']);
        }
        foreach ($report['warnings'] as $metric => $ids) {
            if ($metric === 'standalone_study_materials') {
                continue;
            }
            $details[] = 'WARNING=' . $metric . ': ' . implode(', ', array_map('strval', (array) $ids));
        }
        $defectCount = array_sum(array_map('count', array_filter($report['failures'], 'is_array')));

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
