<?php

declare(strict_types=1);

namespace App\Services\Shared;

/** Validates bounded cognitive-intent inspections and keeps pending labels distinct from approval. */
final class CognitiveIntentReviewService
{
    /** @param list<array<string,mixed>> $questions @param list<array<string,mixed>> $rows @param list<array<string,mixed>> $decisions */
    public static function summarize(array $questions, array $rows, array $decisions): array
    {
        $questionById = [];
        foreach ($questions as $question) {
            if (is_array($question) && is_scalar($question['id'] ?? null)) $questionById[(string) $question['id']] = $question;
        }
        $decisionById = [];
        foreach ($decisions as $decision) {
            if (is_array($decision) && is_scalar($decision['id'] ?? null)) $decisionById[(string) $decision['id']] = $decision;
        }
        $seen = [];
        $byBoard = [];
        $states = [];
        $invalid = [];
        $assigned = 0;
        $reviewRequired = 0;
        foreach ($rows as $index => $row) {
            if (!is_array($row)) {
                $invalid[] = ['row' => $index, 'reason' => 'RECORD_NOT_OBJECT'];
                continue;
            }
            $id = self::text($row['question_id'] ?? null);
            $question = $questionById[$id] ?? null;
            $taxonomy = is_array($question['taxonomy'] ?? null) ? $question['taxonomy'] : [];
            $board = self::text($taxonomy['board_id'] ?? $question['board'] ?? null);
            $concept = self::text($taxonomy['concept_id'] ?? null);
            $intentAfter = self::text($row['intent_after'] ?? null);
            $result = self::text($row['inspection_result'] ?? null);
            $reason = '';
            if ($id === '' || !is_array($question)) $reason = 'UNKNOWN_QUESTION_ID';
            elseif (isset($seen[$id])) $reason = 'DUPLICATE_SAMPLE_QUESTION';
            elseif ($board !== self::text($row['board_id'] ?? null) || $concept !== self::text($row['concept_id'] ?? null)) $reason = 'QUESTION_TAXONOMY_MISMATCH';
            elseif (isset($row['question_snapshot_sha256']) && !hash_equals(AcademicSourceReviewService::questionFingerprint($question), self::text($row['question_snapshot_sha256']))) $reason = 'STALE_QUESTION_SNAPSHOT';
            elseif (self::text($row['reviewer_type'] ?? null) !== 'AI_ASSISTED_EDITORIAL_REVIEW') $reason = 'INVALID_REVIEWER_TYPE';
            elseif (self::text($row['human_signoff'] ?? null) !== 'NOT_RECORDED') $reason = 'UNSUPPORTED_HUMAN_SIGNOFF';
            elseif ($result === 'LABEL_ASSIGNED_PENDING_HUMAN_REVIEW') {
                $decisionId = self::text($row['decision_id'] ?? null);
                $decision = $decisionById[$decisionId] ?? null;
                if ($intentAfter === '' || self::text($question['cognitive_intent'] ?? null) !== $intentAfter) $reason = 'LABEL_NOT_PRESENT_ON_QUESTION';
                elseif (!is_array($decision) || self::text($decision['question_id'] ?? null) !== $id || self::text($decision['review_state'] ?? null) !== 'REVIEW_REQUIRED' || self::text($decision['correction_state'] ?? null) !== 'APPLIED_PENDING_HUMAN_SIGNOFF') $reason = 'PENDING_DECISION_NOT_FOUND';
                elseif (!in_array($intentAfter, ['apply', 'understand', 'analyze'], true)) $reason = 'UNMAPPED_COGNITIVE_LABEL';
            } elseif ($result === 'REVIEW_REQUIRED_NO_AUTOMATED_LABEL') {
                if ($intentAfter !== '' || self::text($question['cognitive_intent'] ?? null) !== '') $reason = 'UNJUSTIFIED_LABEL_ON_REVIEW_REQUIRED_ROW';
            } else $reason = 'INVALID_INSPECTION_RESULT';

            if ($reason !== '') {
                $invalid[] = ['row' => $index, 'question_id' => $id, 'reason' => $reason];
                continue;
            }
            $seen[$id] = true;
            if ($result === 'LABEL_ASSIGNED_PENDING_HUMAN_REVIEW') $assigned++; else $reviewRequired++;
            $byBoard[$board]['inspected'] = ($byBoard[$board]['inspected'] ?? 0) + 1;
            $byBoard[$board][$result] = ($byBoard[$board][$result] ?? 0) + 1;
            $states[$id] = ['board_id' => $board, 'concept_id' => $concept, 'intent_after' => $intentAfter ?: null, 'inspection_result' => $result, 'decision_id' => self::text($row['decision_id'] ?? null) ?: null];
        }
        ksort($byBoard, SORT_STRING);
        ksort($states, SORT_NATURAL);
        return [
            'sample_scope' => 'BATCH_686_SELECTED_SAMPLE_ONLY',
            'inspected_questions' => count($states),
            'label_assigned_pending_human_review' => $assigned,
            'review_required_no_automated_label' => $reviewRequired,
            'human_approved' => 0,
            'by_board' => $byBoard,
            'question_states' => $states,
            'invalid_review_rows' => $invalid,
            'warning' => 'This bounded inspection is not a census. Suggested labels stay pending human academic confirmation.',
        ];
    }

    private static function text(mixed $value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }
}
