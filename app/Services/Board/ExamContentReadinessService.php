<?php

declare(strict_types=1);

namespace App\Services\Board;

/**
 * Computes repository readiness from eligible content, not configured rows.
 */
final class ExamContentReadinessService
{
    public static function evaluate(
        array $board,
        array $questions,
        array $boardSubjects,
        ?array $blueprint = null
    ): array {
        $boardId = strtolower((string) ($board['id'] ?? ''));
        $boardCode = strtolower((string) ($board['code'] ?? ''));
        $eligible = array_values(array_filter($questions, static function (array $question) use ($boardId, $boardCode): bool {
            if (strtolower((string) ($question['status'] ?? 'active')) === 'archived') {
                return false;
            }
            return \App\Services\Question\QuestionEligibilityService::forExam($question, $boardId) !== null
                || ($boardCode !== '' && \App\Services\Question\QuestionEligibilityService::forExam($question, $boardCode) !== null);
        }));

        $relations = [];
        foreach ($boardSubjects as $relation) {
            if (($relation['board_id'] ?? '') === ($board['id'] ?? '')) {
                $relations[(string) ($relation['subject_id'] ?? '')] = true;
            }
        }

        $subjectIds = [];
        $complete = 0;
        foreach ($eligible as $question) {
            $taxonomy = is_array($question['taxonomy'] ?? null) ? $question['taxonomy'] : [];
            $eligibility = \App\Services\Question\QuestionEligibilityService::forExam($question, $boardId)
                ?? \App\Services\Question\QuestionEligibilityService::forExam($question, $boardCode);
            $subjectId = (string) ($eligibility['subject_id'] ?? '');
            if ($subjectId !== '' && ($relations === [] || isset($relations[$subjectId]))) {
                $subjectIds[$subjectId] = true;
            }
            if (self::completeTaxonomy($taxonomy)) {
                $complete++;
            }
        }

        $count = count($eligible);
        $subjectCount = count($subjectIds);
        $taxonomyPercent = $count === 0 ? 0 : (int) round(($complete / $count) * 100);
        $blueprintReady = $blueprint === null || self::hasCoverageTargets($blueprint);

        if ($count === 0) {
            $status = 'EMPTY';
            $reason = 'No eligible questions are available yet.';
        } elseif ($count >= (int) ($board['settings']['readiness']['minimum_questions'] ?? 80)
            && $subjectCount >= (int) ($board['settings']['readiness']['minimum_subjects'] ?? 5)
            && $taxonomyPercent === 100 && $blueprintReady) {
            $status = 'STUDY_READY';
            $reason = 'The repository meets the BoardPrep coverage and taxonomy foundation targets.';
        } elseif ($count >= 20 && $subjectCount >= 2) {
            $status = 'DEVELOPING';
            $reason = 'Multiple subjects have content, but the study foundation is still growing.';
        } else {
            $status = 'FOUNDATION';
            $reason = 'Initial content is available while the repository is being prepared.';
        }

        return [
            'status' => $status,
            'reason' => $reason,
            'eligible_questions' => $count,
            'eligible_subjects' => $subjectCount,
            'taxonomy_completeness' => $taxonomyPercent,
            'blueprint_ready' => $blueprintReady,
        ];
    }

    private static function completeTaxonomy(array $taxonomy): bool
    {
        foreach (['board_id', 'subject_id', 'domain_id', 'topic_id', 'concept_id'] as $key) {
            if (trim((string) ($taxonomy[$key] ?? '')) === '') {
                return false;
            }
        }
        return true;
    }

    private static function hasCoverageTargets(array $blueprint): bool
    {
        return isset($blueprint['sections']) && is_array($blueprint['sections']) && $blueprint['sections'] !== [];
    }
}
