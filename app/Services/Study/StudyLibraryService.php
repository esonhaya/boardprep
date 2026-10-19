<?php

declare(strict_types=1);

namespace App\Services\Study;

use App\Core\App;

final class StudyLibraryService
{
    private static ?\App\Services\Question\QuestionEligibilityIndex $eligibilityIndex = null;
    private static ?array $questionIndex = null;
    private static ?array $examAliasIndex = null;

    public static function all(?string $exam = null): array
    {
        $materials = self::publishedMaterials(App::storage()->all('study-materials'));
        $exam = self::normalizeExam($exam);
        if ($exam !== null && trim($exam) !== '') {
            $materials = array_values(array_filter($materials, static function (array $item) use ($exam): bool {
                return in_array(strtolower(trim($exam)), array_map('strtolower', array_map('strval', $item['exams'] ?? [])), true);
            }));
        }

        return self::enrichMaterials($materials);
    }

    public static function find(string $id): ?array
    {
        foreach (self::publishedMaterials(App::storage()->all('study-materials')) as $material) {
            if ((string) ($material['id'] ?? '') === $id) {
                return self::enrichMaterials([$material])[0];
            }
        }
        return null;
    }

    public static function withSources(array $material): array
    {
        return self::enrichMaterials([$material])[0];
    }

    /** @param array<int, mixed> $materials @return array<int, array<string, mixed>> */
    private static function publishedMaterials(array $materials): array
    {
        return array_values(array_filter(
            $materials,
            static fn(mixed $item): bool => is_array($item)
                && ($item['status'] ?? 'published') === 'published'
        ));
    }

    /**
     * Load each shared lookup collection once, then preserve the existing
     * source-id and material-link order while enriching the requested rows.
     *
     * @param array<int, array<string, mixed>> $materials
     * @return array<int, array<string, mixed>>
     */
    private static function enrichMaterials(array $materials): array
    {
        if ($materials === []) {
            return [];
        }

        $sourcesById = [];
        foreach (SourceRegistryService::all() as $source) {
            $id = (string) ($source['id'] ?? '');
            if (!array_key_exists($id, $sourcesById)) {
                $sourcesById[$id] = $source;
            }
        }

        $linksByMaterial = [];
        foreach (App::storage()->all('study-question-links') as $link) {
            if (!is_array($link)) {
                continue;
            }
            $id = $link['material_id'] ?? '';
            $linksByMaterial[self::strictLookupKey($id)][] = $link;
        }

        $lessonsByMaterial = [];
        foreach (App::storage()->all('study-lessons') as $lesson) {
            if (!is_array($lesson) || ($lesson['status'] ?? '') !== 'published') {
                continue;
            }
            $materialId = is_scalar($lesson['material_id'] ?? null) ? (string) $lesson['material_id'] : '';
            if ($materialId !== '' && !isset($lessonsByMaterial[$materialId])) {
                $lessonsByMaterial[$materialId] = $lesson;
            }
        }

        return array_map(static function (array $material) use ($sourcesById, $linksByMaterial, $lessonsByMaterial): array {
            $materialId = (string) ($material['id'] ?? '');
            $material['sources'] = array_values(array_filter(array_map(
                static fn(mixed $id): ?array => is_scalar($id)
                    ? ($sourcesById[(string) $id] ?? null)
                    : null,
                $material['source_ids'] ?? []
            )));
            $material['question_links'] = $linksByMaterial[
                self::strictLookupKey($material['id'] ?? '')
            ] ?? [];
            $material['lesson'] = $lessonsByMaterial[$materialId] ?? null;
            $material['pdf_url'] = is_array($material['lesson'])
                ? self::publishedPdfUrl($material['lesson']['pdf_file'] ?? null)
                : null;
            return $material;
        }, $materials);
    }

    private static function publishedPdfUrl(mixed $filename): ?string
    {
        if (!is_string($filename) || preg_match('/\A[a-z0-9][a-z0-9-]*\.pdf\z/', $filename) !== 1) {
            return null;
        }
        $directory = realpath(dirname(__DIR__, 3) . '/public/study-resources');
        $path = $directory === false ? false : realpath($directory . DIRECTORY_SEPARATOR . $filename);
        if ($directory === false || $path === false || !str_starts_with($path, $directory . DIRECTORY_SEPARATOR)
            || !is_file($path) || filesize($path) < 8) {
            return null;
        }
        $handle = fopen($path, 'rb');
        $magic = is_resource($handle) ? fread($handle, 5) : false;
        if (is_resource($handle)) fclose($handle);
        return $magic === '%PDF-' ? '/study-resources/' . rawurlencode($filename) : null;
    }

    private static function strictLookupKey(mixed $value): string
    {
        return get_debug_type($value) . ':' . serialize($value);
    }

    public static function questionsFor(string $materialId): array
    {
        return array_values(array_filter(
            App::storage()->all('study-question-links'),
            static fn(mixed $link): bool => is_array($link) && ($link['material_id'] ?? '') === $materialId
        ));
    }

    public static function forQuestion(string $questionId, ?string $boardId = null, ?string $subjectId = null): array
    {
        $questionId = trim($questionId);
        if ($questionId === '') {
            return [];
        }

        return self::forQuestions([$questionId], $boardId, $subjectId)[$questionId] ?? [];
    }

    public static function forQuestions(array $questionIds, ?string $boardId = null, ?string $subjectId = null): array
    {
        $questionIds = array_fill_keys(array_values(array_filter(array_map(
            static fn(mixed $id): string => is_scalar($id) ? trim((string) $id) : '',
            $questionIds
        ))), true);
        if ($questionIds === []) {
            return [];
        }

        $contextBoard = $boardId === null ? '' : (self::normalizeExam($boardId) ?? '');
        $contextSubject = $subjectId === null ? '' : strtolower(trim($subjectId));
        if ($contextSubject === '*') {
            $contextSubject = '';
        }

        $materials = [];
        $materialExams = [];
        foreach (App::storage()->all('study-materials') as $material) {
            if (!is_array($material) || ($material['status'] ?? 'published') !== 'published') {
                continue;
            }
            $materialId = is_scalar($material['id'] ?? null) ? trim((string) $material['id']) : '';
            if ($materialId !== '') {
                $materials[$materialId] = (string) ($material['title'] ?? 'Source-backed study material');
                $materialExams[$materialId] = array_values(array_filter((array) ($material['exams'] ?? []), 'is_scalar'));
            }
        }

        $lessonsByMaterial = [];
        foreach (App::storage()->all('study-lessons') as $lesson) {
            if (!is_array($lesson) || ($lesson['status'] ?? '') !== 'published') {
                continue;
            }
            $materialId = is_scalar($lesson['material_id'] ?? null) ? trim((string) $lesson['material_id']) : '';
            if ($materialId !== '' && !isset($lessonsByMaterial[$materialId])) {
                $lessonsByMaterial[$materialId] = $lesson;
            }
        }

        $references = [];
        foreach (App::storage()->all('study-question-links') as $link) {
            if (!is_array($link)) {
                continue;
            }
            $questionId = is_scalar($link['question_id'] ?? null) ? trim((string) $link['question_id']) : '';
            $materialId = is_scalar($link['material_id'] ?? null) ? trim((string) $link['material_id']) : '';
            if ($questionId === '' || !isset($questionIds[$questionId]) || !isset($materials[$materialId])) {
                continue;
            }
            $reviewGuidance = is_scalar($link['review_guidance'] ?? null)
                ? trim((string) $link['review_guidance'])
                : '';
            $lesson = $lessonsByMaterial[$materialId] ?? null;
            $lessonId = is_array($lesson) && is_scalar($lesson['id'] ?? null) ? trim((string) $lesson['id']) : '';
            if ($contextBoard !== '') {
                if (is_array($lesson)) {
                    if (self::normalizeExam((string) ($lesson['board_id'] ?? '')) !== $contextBoard) {
                        continue;
                    }
                    $lessonSubject = strtolower(trim((string) ($lesson['subject_id'] ?? '')));
                    $resolvedSubject = self::effectiveSubjectForQuestion($questionId, $contextBoard);
                    if ($resolvedSubject === '' || $resolvedSubject !== $lessonSubject
                        || ($contextSubject !== '' && $contextSubject !== $resolvedSubject)) {
                        continue;
                    }
                } else {
                    $materialMatchesBoard = false;
                    foreach ($materialExams[$materialId] ?? [] as $materialExam) {
                        if (self::normalizeExam((string) $materialExam) === $contextBoard) {
                            $materialMatchesBoard = true;
                            break;
                        }
                    }
                    if (!$materialMatchesBoard) {
                        continue;
                    }
                }
            }
            $sectionId = is_scalar($link['section_id'] ?? $link['lesson_section_id'] ?? null)
                ? trim((string) ($link['section_id'] ?? $link['lesson_section_id']))
                : '';
            $lessonIdOnLink = is_scalar($link['lesson_id'] ?? null) ? trim((string) $link['lesson_id']) : '';
            $sectionExists = false;
            if ($lessonId !== '' && ($lessonIdOnLink === '' || $lessonIdOnLink === $lessonId) && $sectionId !== '') {
                foreach ((array) ($lesson['sections'] ?? []) as $section) {
                    if (is_array($section) && (string) ($section['id'] ?? '') === $sectionId) {
                        $sectionExists = true;
                        break;
                    }
                }
            }
            $url = '/study-library?material=' . rawurlencode($materialId);
            if ($lessonId !== '') {
                $url .= '#' . ($sectionExists
                    ? self::lessonSectionAnchorId($lessonId, $sectionId)
                    : 'boardprep-lesson-' . rawurlencode($lessonId));
            }
            $referenceKey = $materialId . "\0" . $reviewGuidance . "\0" . ($sectionExists ? $sectionId : '');
            $references[$questionId][$referenceKey] = [
                'material_id' => $materialId,
                'title' => $materials[$materialId],
                'review_guidance' => $reviewGuidance,
                'source_ids' => array_values(array_filter((array) ($link['source_ids'] ?? []), static fn(mixed $id): bool => is_scalar($id) && trim((string) $id) !== '')),
                'lesson_id' => $lessonId,
                'section_id' => $sectionExists ? $sectionId : '',
                'url' => $url,
            ];
        }

        foreach ($references as $questionId => $items) {
            $references[$questionId] = array_values($items);
        }

        return $references;
    }

    private static function effectiveSubjectForQuestion(string $questionId, string $boardId): string
    {
        if (self::$questionIndex === null) {
            self::$questionIndex = [];
            foreach (App::storage()->all('questions') as $question) {
                if (is_array($question) && is_scalar($question['id'] ?? null)) {
                    self::$questionIndex[(string) $question['id']] ??= $question;
                }
            }
        }
        $question = self::$questionIndex[$questionId] ?? null;
        if (!is_array($question)) {
            return '';
        }
        if (self::$eligibilityIndex === null) {
            $storage = App::storage();
            self::$eligibilityIndex = new \App\Services\Question\QuestionEligibilityIndex(
                $storage->all('question-eligibility'),
                $storage->all('blueprints'),
                \App\Services\Question\QuestionReuseEligibilityResolver::productionDerivedRelationships(),
                $storage->all('question-reuse-overrides')
            );
        }
        $resolved = \App\Services\Question\QuestionEligibilityService::forExam(
            $question,
            $boardId,
            self::$eligibilityIndex
        );
        return is_array($resolved) ? strtolower(trim((string) ($resolved['subject_id'] ?? ''))) : '';
    }

    public static function lessonSectionAnchorId(string $lessonId, string $sectionId): string
    {
        $value = 'boardprep-lesson-' . trim($lessonId) . '-' . trim($sectionId);
        $slug = preg_replace('/[^A-Za-z0-9_-]+/', '-', $value) ?? 'boardprep-lesson-section';
        return trim($slug, '-') . '-' . substr(hash('sha256', $value), 0, 8);
    }

    private static function normalizeExam(?string $exam): ?string
    {
        if ($exam === null) {
            return null;
        }
        $normalize = static fn(string $value): string => strtolower(trim((string) preg_replace('/[\\s_]+/', '-', $value)));
        $exam = $normalize($exam);
        if (self::$examAliasIndex === null) {
            self::$examAliasIndex = [];
            foreach (App::storage()->all('boards') as $board) {
                if (!is_array($board)) continue;
                $boardId = $normalize((string) ($board['id'] ?? ''));
                if ($boardId === '') continue;
                self::$examAliasIndex[$boardId] = $boardId;
                foreach (['code', 'slug', 'short_code'] as $field) {
                    if (is_scalar($board[$field] ?? null) && trim((string) $board[$field]) !== '') {
                        self::$examAliasIndex[$normalize((string) $board[$field])] = $boardId;
                    }
                }
            }
            foreach (['cse' => 'civil-service', 'cle' => 'criminologist', 'nle' => 'nursing', 'ple' => 'psychometrician'] as $alias => $boardId) {
                self::$examAliasIndex[$alias] = $boardId;
            }
        }

        return self::$examAliasIndex[$exam] ?? $exam;
    }

    public static function validate(): array
    {
        return self::validateSnapshot([
            'study-materials' => App::storage()->all('study-materials'),
            'study-question-links' => App::storage()->all('study-question-links'),
            'sources' => SourceRegistryService::all(),
            'questions' => App::storage()->all('questions'),
            'question-eligibility' => App::storage()->all('question-eligibility'),
            'study-lessons' => App::storage()->all('study-lessons'),
        ]);
    }

    /** A deterministic structural validation entry point for repository audits and regression fixtures. */
    public static function validateSnapshot(array $collections): array
    {
        $sources = self::indexById($collections['sources'] ?? []);
        $materials = self::indexById($collections['study-materials'] ?? []);
        $questions = self::indexById($collections['questions'] ?? []);
        $lessons = array_values(array_filter($collections['study-lessons'] ?? [], 'is_array'));
        $issues = [];
        $add = static function (string $recordType, string $id, string $severity, string $reason, string $action) use (&$issues): void {
            $issues[] = ['record_type' => $recordType, 'record_id' => $id !== '' ? $id : '<missing>', 'severity' => $severity, 'reason' => $reason, 'recommended_action' => $action];
        };

        foreach (array_values(array_filter($collections['study-materials'] ?? [], 'is_array')) as $material) {
            $materialId = self::scalarText($material['id'] ?? null);
            if ($materialId === '') $add('study_material', '<missing>', 'ERROR', 'Material has no canonical ID.', 'Assign a unique stable material ID.');
            foreach ((array) ($material['source_ids'] ?? []) as $sourceId) {
                $sourceId = self::scalarText($sourceId);
                if ($sourceId === '' || !isset($sources[$sourceId])) $add('study_material', $materialId, 'ERROR', 'Material references an unknown source: ' . ($sourceId ?: '<empty>') . '.', 'Register the source or repair this source ID.');
            }
        }

        $eligibleBoards = [];
        foreach ((array) ($collections['question-eligibility'] ?? []) as $eligibility) {
            if (!is_array($eligibility)) continue;
            foreach ((array) ($eligibility['question_ids'] ?? []) as $questionId) {
                $key = (string) $questionId;
                foreach ((array) ($eligibility['exam_ids'] ?? []) as $boardId) $eligibleBoards[$key][strtolower((string) $boardId)] = true;
            }
        }

        $seenLinkIds = [];
        $seenPairs = [];
        foreach (array_values(array_filter($collections['study-question-links'] ?? [], 'is_array')) as $link) {
            $linkId = self::scalarText($link['id'] ?? null);
            $questionId = self::scalarText($link['question_id'] ?? null);
            $materialId = self::scalarText($link['material_id'] ?? null);
            if ($linkId === '') $add('study_link', '<missing>', 'ERROR', 'Study link has no canonical ID.', 'Assign a unique stable link ID.');
            elseif (isset($seenLinkIds[$linkId])) $add('study_link', $linkId, 'ERROR', 'Study link ID is duplicated.', 'Retain one canonical link record and give distinct links unique IDs.');
            $seenLinkIds[$linkId] = true;
            if (!isset($questions[$questionId])) $add('study_link', $linkId, 'ERROR', 'Link points to an unknown question: ' . ($questionId ?: '<empty>') . '.', 'Repair the question ID or remove the invalid link.');
            if (!isset($materials[$materialId]) || ($materials[$materialId]['status'] ?? 'published') !== 'published') {
                $add('study_link', $linkId, 'ERROR', 'Link points to a missing or unpublished material: ' . ($materialId ?: '<empty>') . '.', 'Link to a published material or correct its publication status.');
            }
            $sourceIds = $link['source_ids'] ?? null;
            if (!is_array($sourceIds) || $sourceIds === []) {
                $add('study_link', $linkId, 'ERROR', 'Link has no explicit source IDs.', 'Attach the sources that support this question-to-material relationship.');
            } else {
                foreach ($sourceIds as $sourceId) {
                    $sourceId = self::scalarText($sourceId);
                    if ($sourceId === '' || !isset($sources[$sourceId])) $add('study_link', $linkId, 'ERROR', 'Link references an unknown source: ' . ($sourceId ?: '<empty>') . '.', 'Register the source or repair this source ID.');
                }
            }
            $pair = $materialId . "\0" . $questionId;
            if (isset($seenPairs[$pair])) $add('study_link', $linkId, 'ERROR', 'Material/question pair is duplicated.', 'Keep one canonical material-to-question relationship.');
            $seenPairs[$pair] = true;
            if (isset($materials[$materialId], $questions[$questionId]) && isset($eligibleBoards[$questionId])) {
                $materialBoards = array_map('strtolower', array_map('strval', (array) ($materials[$materialId]['exams'] ?? [])));
                if ($materialBoards !== [] && array_intersect($materialBoards, array_keys($eligibleBoards[$questionId])) === []) {
                    $add('study_link', $linkId, 'ERROR', 'Question eligibility does not include any board served by the linked material.', 'Correct board eligibility or link the question to a board-relevant material.');
                }
            }
        }

        $seenLessonIds = [];
        foreach ($lessons as $lesson) {
            $id = self::scalarText($lesson['id'] ?? null);
            $materialId = self::scalarText($lesson['material_id'] ?? null);
            if ($id === '' || isset($seenLessonIds[$id])) $add('study_lesson', $id ?: '<missing>', 'ERROR', 'Lesson ID is missing or duplicated.', 'Assign a unique stable lesson ID.');
            $seenLessonIds[$id] = true;
            if (!isset($materials[$materialId]) || ($materials[$materialId]['status'] ?? 'published') !== 'published') $add('study_lesson', $id, 'ERROR', 'Lesson does not point to a published study material.', 'Repair the material ID or publish the related material.');
            elseif (!in_array(strtolower(self::scalarText($lesson['board_id'] ?? null)), array_map('strtolower', array_map('strval', (array) ($materials[$materialId]['exams'] ?? []))), true)) $add('study_lesson', $id, 'ERROR', 'Lesson board is not served by its linked material.', 'Align the lesson board with the material exam mapping.');
            if (trim(self::scalarText($lesson['scope_note'] ?? null)) === '' || !is_array($lesson['sections'] ?? null) || ($lesson['sections'] ?? []) === []) $add('study_lesson', $id, 'WARNING', 'Lesson is missing its scope note or instructional sections.', 'Complete the exam-scope caveat and teach the concept in structured sections.');
            foreach (array_merge((array) ($lesson['official_scope_source_ids'] ?? []), (array) ($lesson['instructional_source_ids'] ?? [])) as $sourceId) {
                $sourceId = self::scalarText($sourceId);
                if ($sourceId === '' || !isset($sources[$sourceId])) $add('study_lesson', $id, 'ERROR', 'Lesson references an unknown source: ' . ($sourceId ?: '<empty>') . '.', 'Register the source or repair the lesson citation.');
            }
            foreach ((array) ($lesson['practice_question_ids'] ?? []) as $questionId) {
                $questionId = (string) $questionId;
                if (!isset($questions[$questionId])) {
                    $add('study_lesson', $id, 'ERROR', 'Lesson references an unknown practice question: ' . $questionId . '.', 'Use an existing canonical question or remove the invalid reference.');
                    continue;
                }
                if (!isset($seenPairs[$materialId . "\0" . $questionId])) $add('study_lesson', $id, 'ERROR', 'Practice question is not linked to this lesson material: ' . $questionId . '.', 'Create a traceable question-to-material link or remove the practice reference.');
                if (isset($eligibleBoards[$questionId]) && !isset($eligibleBoards[$questionId][strtolower(self::scalarText($lesson['board_id'] ?? null))])) $add('study_lesson', $id, 'ERROR', 'Practice question is not eligible for the lesson board: ' . $questionId . '.', 'Select a board-eligible question or repair its eligibility mapping.');
            }
        }
        $orphans = [];
        $unlinkedPublishedMaterials = [];
        $justifiedStandaloneMaterials = [];
        $linkedMaterials = [];
        foreach (array_values(array_filter($collections['study-question-links'] ?? [], 'is_array')) as $link) $linkedMaterials[self::scalarText($link['material_id'] ?? null)] = true;
        $lessonMaterials = [];
        foreach ($lessons as $lesson) if (($lesson['status'] ?? '') === 'published') $lessonMaterials[self::scalarText($lesson['material_id'] ?? null)] = true;
        foreach ($materials as $materialId => $material) {
            if (($material['status'] ?? 'published') !== 'published' || isset($linkedMaterials[$materialId])) continue;
            $unlinkedPublishedMaterials[] = $materialId;
            if (($material['standalone_resource'] ?? false) === true && self::scalarText($material['standalone_reason'] ?? null) !== '') {
                $justifiedStandaloneMaterials[] = $materialId;
            } else {
                $orphans[] = $materialId;
            }
        }
        $materialsMissingLessons = array_values(array_filter(array_keys($materials), static fn(string $id): bool => ($materials[$id]['status'] ?? 'published') === 'published' && !isset($lessonMaterials[$id])));

        $errors = count(array_filter($issues, static fn(array $issue): bool => $issue['severity'] === 'ERROR'));
        $warnings = count($issues) - $errors;
        return [
            'valid' => $errors === 0,
            'status' => $errors > 0 ? 'FAIL' : ($warnings > 0 ? 'PARTIAL' : 'PASS'),
            'issues' => $issues,
            'invalid' => array_values(array_unique(array_column($issues, 'record_id'))),
            'total' => count($materials),
            'metrics' => [
                'materials' => count($materials),
                'lessons' => count($lessons),
                'links' => count($collections['study-question-links'] ?? []),
                'sources' => count($sources),
                'unlinked_published_materials' => $unlinkedPublishedMaterials,
                'orphan_published_materials' => $orphans,
                'justified_standalone_published_materials' => $justifiedStandaloneMaterials,
                'published_materials_without_lesson' => $materialsMissingLessons,
            ],
        ];
    }

    private static function indexById(mixed $records): array
    {
        $index = [];
        foreach (is_array($records) ? $records : [] as $record) {
            if (is_array($record) && is_scalar($record['id'] ?? null)) $index[(string) $record['id']] ??= $record;
        }
        return $index;
    }

    private static function scalarText(mixed $value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }
}
