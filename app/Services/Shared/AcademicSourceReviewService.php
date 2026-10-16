<?php

declare(strict_types=1);

namespace App\Services\Shared;

/** Summarizes question-level semantic source reviews without treating URL or title presence as verification. */
final class AcademicSourceReviewService
{
    private const CLASSIFICATIONS = ['DIRECT_SUPPORT', 'PARTIAL', 'CONTRADICTED', 'UNKNOWN'];

    /** @param list<array<string,mixed>> $questions @param list<array<string,mixed>> $sources @param list<array<string,mixed>> $reviews @param list<array<string,mixed>> $links @param list<array<string,mixed>> $materials */
    public static function summarize(array $questions, array $sources, array $reviews, array $links = [], array $materials = []): array
    {
        $questionById = [];
        foreach ($questions as $question) {
            if (is_array($question) && is_scalar($question['id'] ?? null)) {
                $questionById[(string) $question['id']] = $question;
            }
        }
        $sourceById = [];
        foreach ($sources as $source) {
            if (is_array($source) && is_scalar($source['id'] ?? null)) {
                $sourceById[(string) $source['id']] = $source;
            }
        }
        $materialById = [];
        foreach ($materials as $material) {
            if (is_array($material) && is_scalar($material['id'] ?? null)) {
                $materialById[(string) $material['id']] = $material;
            }
        }
        $linkedMaterialsByQuestion = [];
        foreach ($links as $link) {
            if (!is_array($link)) continue;
            $questionId = self::text($link['question_id'] ?? null);
            $materialId = self::text($link['material_id'] ?? null);
            if ($questionId !== '' && $materialId !== '') {
                $linkedMaterialsByQuestion[$questionId][$materialId] = $link;
            }
        }

        $counts = array_fill_keys(self::CLASSIFICATIONS, 0);
        $byBoard = [];
        $byQuestion = [];
        $invalidRows = [];
        $seen = [];
        $structurallyLinked = 0;
        foreach ($reviews as $index => $review) {
            if (!is_array($review)) {
                $invalidRows[] = ['row' => $index, 'reason' => 'RECORD_NOT_OBJECT'];
                continue;
            }
            $id = self::text($review['question_id'] ?? null);
            $sourceId = self::text($review['source_id'] ?? null);
            $materialId = self::text($review['material_id'] ?? null);
            $classification = strtoupper(self::text($review['classification'] ?? null));
            $question = $questionById[$id] ?? null;
            $taxonomy = is_array($question['taxonomy'] ?? null) ? $question['taxonomy'] : [];
            $board = self::text($taxonomy['board_id'] ?? $question['board'] ?? null);
            $expectedBoard = self::text($review['board_id'] ?? null);
            $expectedConcept = self::text($review['concept_id'] ?? null);
            $reason = '';
            if ($id === '' || !is_array($question)) {
                $reason = 'UNKNOWN_QUESTION_ID';
            } elseif (isset($seen[$id])) {
                $reason = 'DUPLICATE_QUESTION_REVIEW';
            } elseif ($sourceId === '' || !isset($sourceById[$sourceId])) {
                $reason = 'UNKNOWN_SOURCE_ID';
            } elseif (isset($review['source_snapshot_sha256'])
                && !hash_equals(self::sourceFingerprint($sourceById[$sourceId]), self::text($review['source_snapshot_sha256']))) {
                $reason = 'STALE_SOURCE_SNAPSHOT';
            } elseif (!in_array($classification, self::CLASSIFICATIONS, true)) {
                $reason = 'INVALID_CLASSIFICATION';
            } elseif (trim(self::text($review['source_locator'] ?? null)) === '') {
                $reason = 'MISSING_SOURCE_LOCATOR';
            } elseif (trim(self::text($review['evidence_note'] ?? null)) === '') {
                $reason = 'MISSING_EVIDENCE_NOTE';
            } elseif (trim(self::text($review['limitations'] ?? null)) === '') {
                $reason = 'MISSING_LIMITATIONS';
            } elseif (($review['reviewer_type'] ?? '') !== 'AI_ASSISTED_EDITORIAL_REVIEW') {
                $reason = 'INVALID_REVIEWER_TYPE';
            } elseif (isset($review['question_snapshot_sha256'])
                && !hash_equals(self::questionFingerprint($question), self::text($review['question_snapshot_sha256']))) {
                $reason = 'STALE_QUESTION_SNAPSHOT';
            } elseif ($expectedBoard !== $board || $expectedConcept !== self::text($taxonomy['concept_id'] ?? null)) {
                $reason = 'QUESTION_TAXONOMY_MISMATCH';
            } elseif ($materialId === '' || !isset($materialById[$materialId]) || !isset($linkedMaterialsByQuestion[$id][$materialId])) {
                $reason = 'MATERIAL_NOT_LINKED_TO_QUESTION';
            } elseif (!in_array($sourceId, (array) ($materialById[$materialId]['source_ids'] ?? []), true)
                && !in_array($sourceId, (array) ($linkedMaterialsByQuestion[$id][$materialId]['source_ids'] ?? []), true)) {
                $reason = 'SOURCE_NOT_REGISTERED_ON_LINKED_MATERIAL';
            } elseif (!in_array(self::text($review['review_state'] ?? ''), ['AI_REVIEWED_PENDING_HUMAN_CONFIRMATION', 'REVIEW_REQUIRED'], true)) {
                $reason = 'INVALID_REVIEW_STATE';
            }
            if ($reason !== '') {
                $invalidRows[] = ['row' => $index, 'question_id' => $id, 'reason' => $reason];
                continue;
            }

            $seen[$id] = true;
            $structurallyLinked++;
            $counts[$classification]++;
            $byBoard[$board][$classification] = ($byBoard[$board][$classification] ?? 0) + 1;
            $byBoard[$board]['reviewed_questions'] = ($byBoard[$board]['reviewed_questions'] ?? 0) + 1;
            $byQuestion[$id] = [
                'board_id' => $board,
                'concept_id' => $expectedConcept,
                'classification' => $classification,
                'source_id' => $sourceId,
                'source_title' => self::text($sourceById[$sourceId]['title'] ?? null),
                'source_url' => self::text($sourceById[$sourceId]['url'] ?? null),
                'source_locator' => self::text($review['source_locator']),
                'material_id' => $materialId,
                'review_state' => self::text($review['review_state'] ?? 'REVIEW_REQUIRED'),
                'reviewer_type' => 'AI_ASSISTED_EDITORIAL_REVIEW',
            ];
        }
        ksort($byBoard, SORT_STRING);
        ksort($byQuestion, SORT_NATURAL);
        return [
            'sample_scope' => 'BATCH_686_SELECTED_SAMPLE_ONLY',
            'canonical_question_count' => count($questionById),
            'structurally_linked_review_rows' => $structurallyLinked,
            'reviewed_questions' => count($byQuestion),
            'unreviewed_corpus_questions' => max(0, count($questionById) - count($byQuestion)),
            'classifications' => $counts,
            'by_board' => $byBoard,
            'question_states' => $byQuestion,
            'invalid_review_rows' => $invalidRows,
            'whole_corpus_verified_count' => 0,
            'human_confirmed_rows' => 0,
            'warning' => 'Counts describe only validated adjudications in this selected review sample; linked URLs and source titles are not semantic verification.',
        ];
    }

    private static function text(mixed $value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }

    /** Fingerprint only authored question identity/content/taxonomy; learner usage never changes review freshness. */
    public static function questionFingerprint(array $question): string
    {
        $taxonomy = is_array($question['taxonomy'] ?? null) ? $question['taxonomy'] : [];
        $choices = array_values(array_map(
            static fn(mixed $choice): string => self::text($choice),
            is_array($question['choices'] ?? null) ? $question['choices'] : []
        ));
        $sourceIds = array_values(array_filter(array_map(
            static fn(mixed $sourceId): string => self::text($sourceId),
            is_array($question['source_ids'] ?? null) ? $question['source_ids'] : []
        )));
        sort($sourceIds, SORT_STRING);
        $payload = [
            self::text($question['id'] ?? null),
            self::text($question['question'] ?? null),
            json_encode($choices, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            self::text($question['answer'] ?? null),
            self::text($question['explanation'] ?? null),
            self::text($taxonomy['board_id'] ?? $question['board'] ?? null),
            self::text($taxonomy['subject_id'] ?? null),
            self::text($taxonomy['topic_id'] ?? null),
            self::text($taxonomy['concept_id'] ?? null),
            json_encode($sourceIds, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        ];
        return hash('sha256', implode("\n", $payload));
    }

    /** Link identity/current URL metadata without declaring that metadata semantically supports a question. */
    public static function sourceFingerprint(array $source): string
    {
        return hash('sha256', implode("\n", [
            self::text($source['id'] ?? null),
            self::text($source['title'] ?? null),
            self::text($source['url'] ?? null),
            self::text($source['version'] ?? null),
        ]));
    }
}
