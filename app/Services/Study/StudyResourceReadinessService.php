<?php

declare(strict_types=1);

namespace App\Services\Study;

use App\Services\Question\NumericalAnswerConsistencyAudit;

/** Builds an evidence-graded view of educational content coverage. */
final class StudyResourceReadinessService
{
    public static function analyze(array $collections, array $integrity, array $qa = [], array $priority = []): array
    {
        $questions = array_values(array_filter($collections['questions'] ?? [], 'is_array'));
        $questionById = self::index($questions);
        $materials = self::index($collections['study-materials'] ?? []);
        $sources = self::index($collections['sources'] ?? []);
        $lessonsByMaterial = [];
        foreach (array_values(array_filter($collections['study-lessons'] ?? [], 'is_array')) as $lesson) {
            if (($lesson['status'] ?? '') === 'published') $lessonsByMaterial[(string) ($lesson['material_id'] ?? '')][] = $lesson;
        }
        $linksByQuestion = [];
        foreach (array_values(array_filter($collections['study-question-links'] ?? [], 'is_array')) as $link) {
            $linksByQuestion[(string) ($link['question_id'] ?? '')][] = $link;
        }

        $traceable = $lessonQuestions = $pdfQuestions = [];
        $questionLessonIds = [];
        foreach ($questions as $question) {
            $questionId = self::text($question['id'] ?? null);
            foreach ($linksByQuestion[$questionId] ?? [] as $link) {
                $materialId = self::text($link['material_id'] ?? null);
                $material = $materials[$materialId] ?? null;
                if (!is_array($material) || ($material['status'] ?? 'published') !== 'published') continue;
                $linkSources = (array) ($link['source_ids'] ?? []);
                $traceOk = $linkSources !== [] && !array_filter($linkSources, static fn(mixed $id): bool => !isset($sources[(string) $id]));
                if ($traceOk) $traceable[$questionId] = true;
                foreach ($lessonsByMaterial[$materialId] ?? [] as $lesson) {
                    if (!self::completeLesson($lesson, $sources) || !in_array($questionId, array_map('strval', (array) ($lesson['practice_question_ids'] ?? [])), true)) continue;
                    $lessonQuestions[$questionId] = true;
                    $questionLessonIds[$questionId][$lesson['id']] = true;
                    if (self::hasPdf($lesson)) $pdfQuestions[$questionId] = true;
                }
            }
        }

        $metadataValid = self::validMetadataCount($questions, $collections);
        $numerical = NumericalAnswerConsistencyAudit::analyze($questions);
        $portfolio = is_array($integrity['portfolio'] ?? null) ? $integrity['portfolio'] : [];
        $total = count($questions);
        $qaIssues = array_values(array_filter($qa['issues'] ?? [], 'is_array'));
        $missingIntent = [];
        $suspected = [];
        foreach ($qaIssues as $issue) {
            $code = (string) ($issue['issue_code'] ?? 'UNKNOWN');
            if ($code === 'MISSING_COGNITIVE_INTENT') foreach ((array) ($issue['question_ids'] ?? []) as $id) $missingIntent[(string) $id] = true;
            if (($issue['detection_type'] ?? '') === 'HEURISTIC' || in_array(($issue['severity'] ?? ''), ['REVIEW', 'WARNING'], true)) $suspected[$code] = ($suspected[$code] ?? 0) + 1;
        }

        $validation = StudyLibraryService::validateSnapshot($collections);
        $unlinkedPublishedMaterials = (array) ($validation['metrics']['unlinked_published_materials'] ?? []);
        $orphanPublishedMaterials = (array) ($validation['metrics']['orphan_published_materials'] ?? []);
        $justifiedStandaloneMaterials = (array) ($validation['metrics']['justified_standalone_published_materials'] ?? []);
        $structuralFailures = (int) ($portfolio['total_invalid_eligibility'] ?? 0)
            + (int) ($portfolio['total_missing_eligibility'] ?? 0)
            + (int) ($portfolio['total_invalid_links'] ?? 0)
            + (int) ($portfolio['total_invalid_taxonomy_refs'] ?? 0)
            + (int) ($portfolio['total_invalid_source_refs'] ?? 0)
            + (int) ($portfolio['total_broken_assets'] ?? 0)
            + (int) ($portfolio['total_invalid_answer_keys'] ?? 0)
            + count(array_filter($validation['issues'] ?? [], static fn(array $issue): bool => ($issue['severity'] ?? '') === 'ERROR'));

        $findings = [];
        foreach ($validation['issues'] ?? [] as $issue) {
            $findings[] = ['classification' => 'CONFIRMED_STRUCTURAL_DEFECT', 'record_type' => $issue['record_type'], 'record_id' => $issue['record_id'], 'severity' => $issue['severity'], 'reason' => $issue['reason'], 'recommended_action' => $issue['recommended_action']];
        }
        foreach ($orphanPublishedMaterials as $materialId) {
            $findings[] = [
                'classification' => 'SUSPECTED_ORPHANED_PUBLISHED_RESOURCE',
                'record_type' => 'study_material',
                'record_id' => (string) $materialId,
                'severity' => 'REVIEW',
                'reason' => 'Published material has no question link and no reviewed standalone-resource rationale.',
                'recommended_action' => 'Review the resource and either record its justified standalone purpose or attach relevant canonical questions with source-backed links.',
            ];
        }
        foreach ($qaIssues as $issue) {
            if (($issue['issue_code'] ?? '') === 'MISSING_COGNITIVE_INTENT') {
                $ids = (array) ($issue['question_ids'] ?? []);
                $findings[] = ['classification' => 'CONFIRMED_METADATA_GAP', 'record_type' => 'question', 'record_id' => (string) ($ids[0] ?? '<missing>'), 'record_ids' => array_values(array_map('strval', $ids)), 'severity' => 'REVIEW', 'reason' => (string) ($issue['message'] ?? 'Cognitive intent is missing.'), 'recommended_action' => (string) ($issue['suggested_action'] ?? 'Have an academic reviewer classify the intended reasoning demand.')];
            } elseif (($issue['detection_type'] ?? '') === 'HEURISTIC' || ($issue['severity'] ?? '') === 'REVIEW') {
                $ids = array_values(array_map('strval', (array) ($issue['question_ids'] ?? [])));
                $findings[] = ['classification' => 'SUSPECTED_OR_REVIEW_REQUIRED', 'record_type' => 'question', 'record_id' => (string) ($ids[0] ?? '<missing>'), 'record_ids' => $ids, 'severity' => (string) ($issue['severity'] ?? 'REVIEW'), 'reason' => (string) ($issue['message'] ?? ''), 'recommended_action' => (string) ($issue['suggested_action'] ?? 'Review the affected content.')];
            }
        }
        foreach ($numerical['findings'] as $finding) {
            $findings[] = ['classification' => $finding['class'] === 'HARD_DEFECT' ? 'CONFIRMED_NUMERICAL_DEFECT' : 'SUSPECTED_NUMERICAL_DISAGREEMENT', 'record_type' => 'question', 'record_id' => $finding['entity_id'], 'severity' => $finding['class'] === 'HARD_DEFECT' ? 'ERROR' : 'REVIEW', 'reason' => $finding['note'], 'recommended_action' => 'Verify the question statement, units, calculation inputs, and keyed option before changing content.'];
        }

        $structuralStatus = (string) ($portfolio['status'] ?? 'UNKNOWN');
        $status = $structuralFailures > 0 || $structuralStatus === 'FAIL'
            ? 'FAIL'
            : (($metadataValid < $total || count($lessonQuestions) < $total || count($pdfQuestions) < $total || $qaIssues !== [] || $orphanPublishedMaterials !== [])
                ? 'PARTIAL'
                : (((int) ($portfolio['total_justified_exclusions'] ?? 0) + (int) ($portfolio['total_justified_standalone_study_materials'] ?? 0)) > 0 ? 'PASS_WITH_JUSTIFIED_EXCLUSIONS' : 'PASS'));

        $boardRows = [];
        $boardCatalog = self::index($collections['boards'] ?? []);
        $integrityBoards = is_array($integrity['boards'] ?? null) ? $integrity['boards'] : [];
        foreach ($boardCatalog as $boardId => $board) {
            $boardQuestions = array_values(array_filter($questions, static function (array $question) use ($boardId): bool {
                $taxonomy = is_array($question['taxonomy'] ?? null) ? $question['taxonomy'] : [];
                return strtolower(self::text($taxonomy['board_id'] ?? $question['board'] ?? null)) === strtolower($boardId);
            }));
            $boardIds = array_fill_keys(array_map(static fn(array $question): string => self::text($question['id'] ?? null), $boardQuestions), true);
            $linkedLessons = count(array_intersect_key($lessonQuestions, $boardIds));
            $linkedPdfs = count(array_intersect_key($pdfQuestions, $boardIds));
            $baseStatus = (string) (
                $integrityBoards[$boardId]['readiness_status']
                ?? $integrityBoards[$boardId]['status']
                ?? 'UNKNOWN'
            );
            $boardStatus = in_array($baseStatus, ['FAIL', 'UNKNOWN', 'NOT_RUN'], true)
                ? $baseStatus
                : (($linkedLessons < count($boardQuestions) || $linkedPdfs < count($boardQuestions)) ? 'PARTIAL' : $baseStatus);
            $boardRows[$boardId] = [
                'board_id' => $boardId,
                'board_name' => self::text($board['name'] ?? $boardId),
                'status' => $boardStatus,
                'canonical_questions' => count($boardQuestions),
                'traceable_source_questions' => count(array_intersect_key($traceable, $boardIds)),
                'lesson_practice_questions' => $linkedLessons,
                'pdf_practice_questions' => $linkedPdfs,
                'integrity' => $integrityBoards[$boardId] ?? ['status' => 'UNKNOWN', 'defects' => []],
            ];
        }

        $academicReviewIds = $missingIntent;
        foreach ($findings as $finding) {
            if (($finding['record_type'] ?? '') !== 'question') continue;
            if (!str_starts_with((string) ($finding['classification'] ?? ''), 'SUSPECTED_')) continue;
            foreach ((array) ($finding['record_ids'] ?? [$finding['record_id'] ?? '']) as $recordId) $academicReviewIds[(string) $recordId] = true;
        }
        $numericalMetrics = $numerical['metrics'];
        $standaloneResourceRecords = array_values(array_filter(array_map(static function (mixed $id) use ($materials): array {
            $materialId = (string) $id;
            $material = $materials[$materialId] ?? [];
            return [
                'id' => $materialId,
                'title' => self::text($material['title'] ?? $materialId),
                'reason' => self::text($material['standalone_reason'] ?? ''),
            ];
        }, $justifiedStandaloneMaterials), static fn(array $record): bool => $record['reason'] !== ''));
        return [
            'status' => $status,
            'status_vocabulary' => ['PASS', 'PASS_WITH_JUSTIFIED_EXCLUSIONS', 'PARTIAL', 'FAIL', 'NOT_RUN', 'UNKNOWN'],
            'evidence_limits' => [
                'source_traceability_is_structural' => true,
                'semantic_source_relevance' => 'UNKNOWN unless a question-specific reviewed fit record explicitly establishes it; a registered URL alone is not verification.',
                'cognitive_intent_is_metadata_not_psychometric_calibration' => true,
                'numerical_models_are_counted_only_when_a_supported_structured_formula_recomputes' => true,
            ],
            'metrics' => [
                'canonical_questions' => $total,
                'questions_with_registered_source_traceability' => count($traceable),
                'questions_with_semantically_verified_source_support' => ['count' => 0, 'status' => 'UNKNOWN', 'reason' => 'No question-level semantic source review is encoded as a verified state in this readiness view.'],
                'questions_linked_to_complete_boardprep_lessons' => count($lessonQuestions),
                'questions_with_downloadable_study_pdfs' => count($pdfQuestions),
                'questions_with_valid_metadata' => $metadataValid,
                'questions_missing_cognitive_intent' => count($missingIntent),
                'questions_requiring_academic_or_editorial_review' => count(array_filter(array_keys($academicReviewIds), static fn(string $id): bool => $id !== '')),
                'numerical_verification' => $numericalMetrics + ['coverage_percent' => $numericalMetrics['structured_payloads'] > 0 ? round(100 * $numericalMetrics['recomputed'] / $numericalMetrics['structured_payloads'], 2) : 0],
                'flashcards' => ['derivable' => (int) ($portfolio['total_derivable_cards'] ?? 0), 'justified_exclusions' => (int) ($portfolio['total_justified_exclusions'] ?? 0), 'unexpected_derivation_failures' => (int) ($portfolio['total_accidental_non_derivable'] ?? 0)],
                'taxonomy_and_eligibility_problems' => ['invalid_taxonomy_references' => (int) ($portfolio['total_invalid_taxonomy_refs'] ?? 0), 'missing_eligibility' => (int) ($portfolio['total_missing_eligibility'] ?? 0), 'invalid_eligibility' => (int) ($portfolio['total_invalid_eligibility'] ?? 0)],
                'broken_references_and_assets' => ['invalid_source_references' => (int) ($portfolio['total_invalid_source_refs'] ?? 0), 'invalid_study_links' => (int) ($portfolio['total_invalid_links'] ?? 0), 'broken_assets' => (int) ($portfolio['total_broken_assets'] ?? 0)],
                'unlinked_published_materials' => $unlinkedPublishedMaterials,
                'orphan_published_materials' => $orphanPublishedMaterials,
                'justified_exclusions' => [
                    'flashcards' => (int) ($portfolio['total_justified_exclusions'] ?? 0),
                    'standalone_resources' => count($justifiedStandaloneMaterials),
                    'standalone_resource_ids' => $justifiedStandaloneMaterials,
                    'standalone_resource_records' => $standaloneResourceRecords,
                ],
                'defect_classification' => ['confirmed_structural_findings' => count(array_filter($findings, static fn(array $f): bool => str_starts_with((string) ($f['classification'] ?? ''), 'CONFIRMED_'))), 'suspected_or_review_findings' => count(array_filter($findings, static fn(array $f): bool => str_starts_with((string) ($f['classification'] ?? ''), 'SUSPECTED_')))],
            ],
            'board_readiness' => $boardRows,
            'findings' => $findings,
            'reporting_status' => isset($qa['summary']) && is_array($qa['summary']) ? 'PASS' : 'NOT_RUN',
            'limits' => ['human_editorial_signoff' => 'NOT_RECORDED', 'browser_visual_validation' => 'NOT_RUN_BY_THIS_SERVICE'],
        ];
    }

    private static function validMetadataCount(array $questions, array $collections): int
    {
        $catalogs = [
            'board_id' => self::index($collections['boards'] ?? []),
            'subject_id' => self::index($collections['subjects'] ?? []),
            'domain_id' => self::index($collections['taxonomy/domains'] ?? []),
            'topic_id' => self::index($collections['taxonomy/topics'] ?? []),
            'concept_id' => self::index($collections['taxonomy/concepts'] ?? []),
        ];
        $valid = 0;
        foreach ($questions as $question) {
            $taxonomy = is_array($question['taxonomy'] ?? null) ? $question['taxonomy'] : [];
            $complete = true;
            foreach ($catalogs as $key => $catalog) {
                $id = self::text($taxonomy[$key] ?? null);
                if ($id === '' || !isset($catalog[$id])) { $complete = false; break; }
            }
            if (!$complete || self::text($question['cognitive_intent'] ?? null) === ''
                || !in_array(strtolower(self::text($question['difficulty'] ?? null)), ['easy', 'medium', 'hard'], true)) continue;
            $valid++;
        }
        return $valid;
    }

    private static function completeLesson(array $lesson, array $sources): bool
    {
        if (self::text($lesson['title'] ?? null) === '' || self::text($lesson['scope_note'] ?? null) === ''
            || empty($lesson['learning_objectives']) || empty($lesson['sections']) || empty($lesson['worked_examples'])
            || empty($lesson['common_misconceptions']) || empty($lesson['question_patterns']) || empty($lesson['practice_question_ids'])) return false;
        $sourceIds = array_merge((array) ($lesson['official_scope_source_ids'] ?? []), (array) ($lesson['instructional_source_ids'] ?? []));
        return $sourceIds !== [] && !array_filter($sourceIds, static fn(mixed $id): bool => !isset($sources[(string) $id]));
    }

    private static function hasPdf(array $lesson): bool
    {
        $filename = self::text($lesson['pdf_file'] ?? null);
        if (preg_match('/\A[a-z0-9][a-z0-9-]*\.pdf\z/', $filename) !== 1) return false;
        $path = dirname(__DIR__, 3) . '/public/study-resources/' . $filename;
        if (!is_file($path)) return false;
        $handle = fopen($path, 'rb');
        $magic = is_resource($handle) ? fread($handle, 5) : false;
        if (is_resource($handle)) fclose($handle);
        return $magic === '%PDF-';
    }

    private static function index(mixed $records): array
    {
        $out = [];
        foreach (is_array($records) ? $records : [] as $record) if (is_array($record) && is_scalar($record['id'] ?? null)) $out[(string) $record['id']] ??= $record;
        return $out;
    }

    private static function text(mixed $value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }
}
