<?php

declare(strict_types=1);

namespace App\Services\Question\Quality;

final class StudyRelationshipSummary
{
    /** @return array<string,int> */
    public static function build(array $questions, array $links, array $lessons = []): array
    {
        $linksByQuestion = [];
        foreach ($links as $link) {
            if (!is_array($link) || trim((string) ($link['question_id'] ?? '')) === '') continue;
            $review = strtoupper(trim((string) ($link['semantic_review_status'] ?? $link['review_status'] ?? $link['status'] ?? '')));
            if (in_array($review, ['REJECTED', 'MAPPING_REJECTED', 'REJECTED_MAPPING'], true)) continue;
            $linksByQuestion[(string) $link['question_id']] = true;
        }

        $sourceOnlyQuestions = 0;
        foreach ($questions as $question) {
            if (!is_array($question)) continue;
            $id = trim((string) ($question['id'] ?? ''));
            $sourceIds = array_values(array_filter((array) ($question['source_ids'] ?? []), static fn(mixed $sourceId): bool => is_scalar($sourceId) && trim((string) $sourceId) !== ''));
            if ($sourceIds !== [] && !isset($linksByQuestion[$id])) $sourceOnlyQuestions++;
        }

        $materialOnly = 0;
        $exact = 0;
        $sourceOnlyRows = 0;
        $rejected = 0;
        $pending = 0;
        $unknown = 0;
        $approved = 0;
        $brokenSections = 0;
        $lessonsById = [];
        $lessonsByMaterial = [];
        foreach ($lessons as $lesson) {
            if (!is_array($lesson) || ($lesson['status'] ?? '') !== 'published') continue;
            $lessonId = trim((string) ($lesson['id'] ?? ''));
            $materialId = trim((string) ($lesson['material_id'] ?? ''));
            if ($lessonId !== '') $lessonsById[$lessonId] = $lesson;
            if ($materialId !== '') $lessonsByMaterial[$materialId][] = $lesson;
        }
        foreach ($links as $link) {
            if (!is_array($link)) continue;
            $review = strtoupper(trim((string) ($link['semantic_review_status'] ?? $link['review_status'] ?? $link['status'] ?? '')));
            if (in_array($review, ['REJECTED', 'MAPPING_REJECTED', 'REJECTED_MAPPING'], true)) {
                $rejected++;
                continue;
            }
            $materialId = trim((string) ($link['material_id'] ?? ''));
            $sectionId = trim((string) ($link['section_id'] ?? $link['lesson_section_id'] ?? ''));
            if ($sectionId !== '') {
                $lessonId = trim((string) ($link['lesson_id'] ?? ''));
                $candidates = $lessonId !== ''
                    ? (isset($lessonsById[$lessonId]) ? [$lessonsById[$lessonId]] : [])
                    : ($lessonsByMaterial[$materialId] ?? []);
                $sectionExists = false;
                foreach ($candidates as $lesson) {
                    if ($lessonId !== '' && (string) ($lesson['material_id'] ?? '') !== $materialId) continue;
                    foreach ((array) ($lesson['sections'] ?? []) as $section) {
                        if (is_array($section) && (string) ($section['id'] ?? '') === $sectionId) {
                            $sectionExists = true;
                            break 2;
                        }
                    }
                }
                if (!$sectionExists) {
                    $brokenSections++;
                    continue;
                }
                $exact++;
                if ($review === 'APPROVED_BY_AUTHORIZED_REVIEWER' && trim((string) ($link['approval_evidence_id'] ?? $link['approval_evidence'] ?? '')) !== '') {
                    $approved++;
                } elseif (str_contains($review, 'PENDING')) {
                    $pending++;
                } else {
                    $unknown++;
                }
            } elseif ($materialId !== '') {
                $materialOnly++;
            } elseif ((array) ($link['source_ids'] ?? []) !== []) {
                $sourceOnlyRows++;
            }
        }

        return [
            'canonical_questions_with_source_only_references' => $sourceOnlyQuestions,
            'source_only_link_rows' => $sourceOnlyRows,
            'material_level_link_rows' => $materialOnly,
            'exact_section_link_rows' => $exact,
            'broken_or_unresolved_section_link_rows' => $brokenSections,
            'exact_links_pending_or_approval_evidence_missing' => $pending,
            'exact_links_unreviewed_or_status_unknown' => $unknown,
            'exact_links_approved_with_explicit_evidence' => $approved,
            'explicitly_rejected_mapping_rows' => $rejected,
        ];
    }
}
