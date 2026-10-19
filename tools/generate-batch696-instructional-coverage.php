#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

$options = [];
foreach (array_slice($argv, 1) as $argument) {
    if (str_starts_with($argument, '--json=')) $options['json'] = substr($argument, 7);
    elseif (str_starts_with($argument, '--markdown=')) $options['markdown'] = substr($argument, 11);
    elseif ($argument === '--authoritative-baseline') $options['authoritative_baseline'] = true;
    else {
        fwrite(STDERR, "Usage: php tools/generate-batch696-instructional-coverage.php --json=PATH --markdown=PATH [--authoritative-baseline]\n");
        exit(2);
    }
}
if (empty($options['json']) || empty($options['markdown'])) {
    fwrite(STDERR, "Both --json and --markdown output paths are required.\n");
    exit(2);
}

putenv('APP_ENV=testing');
putenv('APP_DEBUG=false');
\App\Core\App::boot();
$storage = \App\Core\App::storage();
$boards = $storage->all('boards');
$subjects = $storage->all('subjects');
$questions = $storage->all('questions');
$materials = $storage->all('study-materials');
$lessons = $storage->all('study-lessons');
$links = $storage->all('study-question-links');
$sources = $storage->all('sources');
$topics = $storage->all('taxonomy/topics');
$concepts = $storage->all('taxonomy/concepts');
$blueprints = $storage->all('blueprints');
$eligibility = new \App\Services\Question\QuestionEligibilityIndex(
    $storage->all('question-eligibility'),
    $blueprints,
    \App\Services\Question\QuestionReuseEligibilityResolver::productionDerivedRelationships(),
    $storage->all('question-reuse-overrides')
);

$index = static function (array $records): array {
    $result = [];
    foreach ($records as $record) {
        if (is_array($record) && is_scalar($record['id'] ?? null)) $result[(string) $record['id']] ??= $record;
    }
    return $result;
};
$boardById = $index($boards);
$subjectById = $index($subjects);
$questionById = $index($questions);
$materialById = $index($materials);
$sourceById = $index($sources);
$topicById = $index($topics);
$conceptById = $index($concepts);
$manifestPath = dirname(__DIR__) . '/public/study-resources/manifest.json';
$manifest = is_file($manifestPath) ? json_decode((string) file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR) : [];
$manifestByLesson = [];
foreach ((array) ($manifest['files'] ?? []) as $entry) {
    if (is_array($entry)) $manifestByLesson[(string) ($entry['lesson_id'] ?? '')] = $entry;
}

$lessonsByScope = [];
$lessonByMaterial = [];
foreach ($lessons as $lesson) {
    if (!is_array($lesson) || ($lesson['status'] ?? '') !== 'published') continue;
    $scope = strtolower((string) ($lesson['board_id'] ?? '')) . '|' . strtolower((string) ($lesson['subject_id'] ?? ''));
    $lessonsByScope[$scope][] = $lesson;
    $materialId = (string) ($lesson['material_id'] ?? '');
    if ($materialId !== '') $lessonByMaterial[$materialId] ??= $lesson;
}
$linksByQuestion = [];
foreach ($links as $link) {
    if (is_array($link)) $linksByQuestion[(string) ($link['question_id'] ?? '')][] = $link;
}

$lessonHasCompletePdf = static function (array $lesson) use ($manifestByLesson): bool {
    $entry = $manifestByLesson[(string) ($lesson['id'] ?? '')] ?? null;
    if (!is_array($entry) || !is_string($entry['path'] ?? null) || !is_string($entry['sha256'] ?? null)) return false;
    $path = dirname(__DIR__) . '/' . $entry['path'];
    return is_file($path) && hash_equals($entry['sha256'], (string) hash_file('sha256', $path));
};
$isVerifiedSource = static function (mixed $id) use ($sourceById): bool {
    $source = $sourceById[(string) $id] ?? null;
    return is_array($source) && in_array(strtolower((string) ($source['status'] ?? '')), ['verified_reference', 'verified', 'verified_primary_source'], true);
};

$rows = [];
$activeBoardIds = [];
$scopeSeen = [];
$practiceBoards = \App\Services\Board\BoardViewService::all();
foreach ($practiceBoards as $practiceBoard) {
    if (!is_array($practiceBoard)) continue;
    $boardId = strtolower((string) ($practiceBoard['id'] ?? ''));
    $board = $boardById[$boardId] ?? null;
    if (!is_array($board)) continue;
    $boardStatus = strtolower((string) ($board['status'] ?? $board['active'] ?? 'active'));
    if (in_array($boardStatus, ['inactive', 'archived', 'disabled'], true)) continue;
    $activeBoardIds[$boardId] = true;
    foreach ((array) ($practiceBoard['practice_subjects'] ?? []) as $practiceSubject) {
        if (!is_array($practiceSubject)) continue;
        $subjectId = strtolower((string) ($practiceSubject['id'] ?? ''));
        if ($subjectId === '' || (int) ($practiceSubject['available_questions'] ?? 0) < 1) continue;
        $scopeKey = $boardId . '|' . $subjectId;
        if (isset($scopeSeen[$scopeKey])) continue;
        $scopeSeen[$scopeKey] = true;
        $subject = $subjectById[$subjectId] ?? ['id' => $subjectId, 'name' => (string) ($practiceSubject['name'] ?? $subjectId)];
        if (!is_array($subject) || in_array(strtolower((string) ($subject['status'] ?? $subject['active'] ?? 'active')), ['inactive', 'archived', 'disabled'], true)) continue;

        $effective = [];
        foreach ($questions as $question) {
            if (!is_array($question) || !is_scalar($question['id'] ?? null) || strtolower((string) ($question['status'] ?? 'active')) === 'archived') continue;
            $resolved = \App\Services\Question\QuestionEligibilityService::forExam($question, $boardId, $eligibility);
            if ($resolved === null && is_string($practiceBoard['code'] ?? null) && $practiceBoard['code'] !== '') {
                $resolved = \App\Services\Question\QuestionEligibilityService::forExam($question, $practiceBoard['code'], $eligibility);
            }
            if (strtolower((string) ($resolved['subject_id'] ?? '')) === $subjectId) $effective[(string) $question['id']] = $question;
        }

        $scopeLessons = $lessonsByScope[$scopeKey] ?? [];
        $linkedQuestionIds = [];
        $sourceOnlyQuestionIds = [];
        $semanticCandidates = [];
        $brokenLessonLinks = [];
        $verifiedSourceQuestionIds = [];
        $registeredSourceQuestionIds = [];
        $topicsPresent = [];
        $conceptsPresent = [];
        $explanationsAvailable = [];

        foreach ($effective as $questionId => $question) {
            $taxonomy = is_array($question['taxonomy'] ?? null) ? $question['taxonomy'] : [];
            $topicId = (string) ($taxonomy['topic_id'] ?? $question['topic_id'] ?? $question['topic'] ?? '');
            $conceptId = (string) ($taxonomy['concept_id'] ?? $question['concept_id'] ?? $question['concept'] ?? '');
            if ($topicId !== '') $topicsPresent[$topicId] = true;
            if ($conceptId !== '') $conceptsPresent[$conceptId] = true;
            if (trim((string) ($question['explanation'] ?? '')) !== '') $explanationsAvailable[$questionId] = true;

            $hasLesson = false;
            $hasRegisteredSource = false;
            $hasVerifiedSource = false;
            foreach ($linksByQuestion[$questionId] ?? [] as $link) {
                $materialId = (string) ($link['material_id'] ?? '');
                $material = $materialById[$materialId] ?? null;
                $lesson = $lessonByMaterial[$materialId] ?? null;
                foreach ((array) ($link['source_ids'] ?? []) as $sourceId) {
                    if (isset($sourceById[(string) $sourceId])) $hasRegisteredSource = true;
                    if ($isVerifiedSource($sourceId)) $hasVerifiedSource = true;
                }
                if (is_array($lesson)
                    && is_array($material)
                    && strtolower((string) ($lesson['board_id'] ?? '')) === $boardId
                    && strtolower((string) ($lesson['subject_id'] ?? '')) === $subjectId) {
                    $hasLesson = true;
                    if (str_contains(strtoupper((string) ($link['semantic_review_status'] ?? '')), 'PENDING')) {
                        $semanticCandidates[$questionId] = true;
                    }
                    $sectionId = (string) ($link['section_id'] ?? $link['lesson_section_id'] ?? '');
                    if ($sectionId === '') {
                        $semanticCandidates[$questionId] = true;
                    } else {
                        $sectionExists = false;
                        foreach ((array) ($lesson['sections'] ?? []) as $section) {
                            if (is_array($section) && (string) ($section['id'] ?? '') === $sectionId) $sectionExists = true;
                        }
                        if (!$sectionExists) $brokenLessonLinks[$questionId] = true;
                        else $linkedQuestionIds[$questionId] = true;
                    }
                } elseif (!is_array($lesson)
                    && (($link['lesson_id'] ?? '') !== '' || ($link['section_id'] ?? $link['lesson_section_id'] ?? '') !== '')) {
                    $brokenLessonLinks[$questionId] = true;
                }
            }
            foreach ($scopeLessons as $lesson) {
                if (in_array($questionId, array_map('strval', (array) ($lesson['practice_question_ids'] ?? [])), true)) {
                    $hasLesson = true;
                    $sectionIds = [];
                    foreach ((array) ($lesson['sections'] ?? []) as $section) {
                        if (is_array($section) && in_array($conceptId, array_map('strval', (array) ($section['related_concept_ids'] ?? [])), true)) $sectionIds[] = $section['id'] ?? null;
                    }
                    if ($sectionIds === []) $semanticCandidates[$questionId] = true;
                    else $linkedQuestionIds[$questionId] = true;
                }
            }
            if ($hasLesson) $linkedQuestionIds[$questionId] = true;
            else $sourceOnlyQuestionIds[$questionId] = true;
            if ($hasRegisteredSource) $registeredSourceQuestionIds[$questionId] = true;
            if ($hasVerifiedSource) $verifiedSourceQuestionIds[$questionId] = true;
        }

        $conceptInstruction = [];
        $substantiveSectionCount = 0;
        $substantiveByLesson = [];
        foreach ($scopeLessons as $lesson) {
            $currentLessonId = (string) ($lesson['id'] ?? '');
            $lessonTaxonomy = is_array($lesson['taxonomy'] ?? null) ? $lesson['taxonomy'] : [];
            foreach ((array) ($lesson['sections'] ?? []) as $section) {
                if (!is_array($section)) continue;
                $body = trim((string) ($section['body'] ?? ''));
                $items = array_values(array_filter((array) ($section['items'] ?? []), static fn(mixed $item): bool => is_scalar($item) && trim((string) $item) !== ''));
                $conceptIds = array_values(array_unique(array_merge(
                    array_map('strval', (array) ($section['related_concept_ids'] ?? [])),
                    array_map('strval', (array) ($lessonTaxonomy['concept_ids'] ?? [])),
                    isset($lessonTaxonomy['concept_id']) ? [(string) $lessonTaxonomy['concept_id']] : []
                )));
                if (mb_strlen($body) >= 180 && ($items !== [] || mb_strlen($body) >= 360)) {
                    $substantiveSectionCount++;
                    $substantiveByLesson[$currentLessonId] = ($substantiveByLesson[$currentLessonId] ?? 0) + 1;
                    foreach ($conceptIds as $conceptId) if (isset($conceptsPresent[$conceptId])) $conceptInstruction[$conceptId] = true;
                }
            }
        }

        $completeExamples = 0;
        $pdfCount = 0;
        $lessonRows = [];
        foreach ($scopeLessons as $lesson) {
            $lessonExampleCount = 0;
            foreach ((array) ($lesson['worked_examples'] ?? []) as $example) {
                if (is_array($example) && trim((string) ($example['prompt'] ?? '')) !== '' && count(array_filter((array) ($example['steps'] ?? []), 'is_string')) > 0
                    && trim((string) ($example['answer'] ?? '')) !== '' && trim((string) ($example['check'] ?? '')) !== '') {
                    $completeExamples++;
                    $lessonExampleCount++;
                }
            }
            $hasPdf = $lessonHasCompletePdf($lesson);
            if ($hasPdf) $pdfCount++;
            $lessonRows[] = ['id' => (string) ($lesson['id'] ?? ''), 'title' => (string) ($lesson['title'] ?? ''), 'section_count' => count((array) ($lesson['sections'] ?? [])), 'substantive_section_count' => (int) ($substantiveByLesson[(string) ($lesson['id'] ?? '')] ?? 0), 'worked_example_count' => $lessonExampleCount, 'learning_objective_count' => count((array) ($lesson['learning_objectives'] ?? [])), 'pdf_available' => $hasPdf];
        }

        $sourceIds = [];
        foreach (array_keys($effective) as $questionId) {
            foreach ($linksByQuestion[$questionId] ?? [] as $link) {
                foreach ((array) ($link['source_ids'] ?? []) as $sourceId) if (isset($sourceById[(string) $sourceId])) $sourceIds[(string) $sourceId] = true;
            }
        }
        $verifiedSources = array_values(array_filter(array_keys($sourceIds), $isVerifiedSource));
        $missingConceptIds = array_values(array_diff(array_keys($conceptsPresent), array_keys($conceptInstruction)));
        $missingPdfLessonIds = array_values(array_map(
            static fn(array $lesson): string => (string) ($lesson['id'] ?? ''),
            array_values(array_filter($scopeLessons, static fn(array $lesson): bool => !$lessonHasCompletePdf($lesson)))
        ));
        $shallowLessonIds = array_values(array_map(
            static fn(array $lesson): string => (string) ($lesson['id'] ?? ''),
            array_values(array_filter($lessonRows, static fn(array $lesson): bool => $lesson['substantive_section_count'] < 1 || $lesson['section_count'] < 2 || $lesson['worked_example_count'] < 1 || $lesson['learning_objective_count'] < 1))
        ));
        $diagnostics = [];
        if ($scopeLessons === []) $diagnostics[] = ['class'=>'DETERMINISTIC_FAILURE','code'=>'SUBJECT_WITHOUT_LESSONS','record_type'=>'subject','record_ids'=>[$boardId . '|' . $subjectId],'action'=>'Add an original lesson that teaches the subject competencies.'];
        if ($missingConceptIds !== []) $diagnostics[] = ['class'=>'HEURISTIC_REVIEW_CANDIDATE','code'=>'CONCEPT_WITHOUT_INSTRUCTION','record_type'=>'concept','record_ids'=>$missingConceptIds,'action'=>'Review the blueprint and add a substantive section where coverage is required.'];
        if ($sourceOnlyQuestionIds !== []) $diagnostics[] = ['class'=>'DETERMINISTIC_FAILURE','code'=>'QUESTION_MISSING_LESSON_LINK','record_type'=>'question','record_ids'=>array_keys($sourceOnlyQuestionIds),'action'=>'Link only after the exact question concept is taught in a verified lesson section.'];
        if ($brokenLessonLinks !== []) $diagnostics[] = ['class'=>'DETERMINISTIC_FAILURE','code'=>'QUESTION_LESSON_SECTION_MISSING','record_type'=>'question','record_ids'=>array_keys($brokenLessonLinks),'action'=>'Repair the same-scope lesson and section relationship.'];
        if ($semanticCandidates !== []) $diagnostics[] = ['class'=>'HEURISTIC_REVIEW_CANDIDATE','code'=>'QUESTION_LESSON_SEMANTIC_REVIEW','record_type'=>'question','record_ids'=>array_keys($semanticCandidates),'action'=>'Human reviewers must compare the stem, answer, explanation, lesson section, and source evidence.'];
        if ($missingPdfLessonIds !== []) $diagnostics[] = ['class'=>'DETERMINISTIC_FAILURE','code'=>'STUDY_PDF_MISSING','record_type'=>'lesson','record_ids'=>$missingPdfLessonIds,'action'=>'Generate and verify a PDF and manifest entry for the published lesson.'];
        if ($shallowLessonIds !== []) $diagnostics[] = ['class'=>'HEURISTIC_REVIEW_CANDIDATE','code'=>'STUDY_LESSON_TOO_SHALLOW_REVIEW','record_type'=>'lesson','record_ids'=>$shallowLessonIds,'action'=>'Review learning objectives, section substance, and worked application; text structure alone cannot establish instructional adequacy.'];
        $rows[] = [
            'board_id' => $boardId,
            'board_name' => (string) ($board['name'] ?? $boardId),
            'subject_id' => $subjectId,
            'subject_name' => (string) ($subject['name'] ?? $practiceSubject['name'] ?? $subjectId),
            'effective_question_count' => count($effective),
            'topic_count' => count($topicsPresent),
            'topic_ids' => array_keys($topicsPresent),
            'concept_count' => count($conceptsPresent),
            'concept_ids' => array_keys($conceptsPresent),
            'existing_lessons' => $lessonRows,
            'existing_lesson_count' => count($scopeLessons),
            'existing_lesson_sections' => array_sum(array_column($lessonRows, 'section_count')),
            'substantive_lesson_sections' => $substantiveSectionCount,
            'downloadable_pdfs' => $pdfCount,
            'lesson_linked_questions' => count($linkedQuestionIds),
            'lesson_linked_question_ids' => array_keys($linkedQuestionIds),
            'source_only_questions' => count($sourceOnlyQuestionIds),
            'source_only_question_ids' => array_keys($sourceOnlyQuestionIds),
            'source_only_with_registered_source' => count(array_intersect_key($sourceOnlyQuestionIds, $registeredSourceQuestionIds)),
            'broken_lesson_links' => count($brokenLessonLinks),
            'broken_lesson_question_ids' => array_keys($brokenLessonLinks),
            'semantic_link_review_candidates' => count($semanticCandidates),
            'semantic_link_review_question_ids' => array_keys($semanticCandidates),
            'concepts_with_real_instruction' => count($conceptInstruction),
            'concepts_without_instruction' => max(0, count($conceptsPresent) - count($conceptInstruction)),
            'concepts_with_instruction_ids' => array_keys($conceptInstruction),
            'concepts_without_instruction_ids' => array_values(array_diff(array_keys($conceptsPresent), array_keys($conceptInstruction))),
            'worked_examples_available' => $completeExamples,
            'practice_explanations_available' => count($explanationsAvailable),
            'registered_verified_source_ids' => $verifiedSources,
            'verified_source_coverage' => count($verifiedSourceQuestionIds),
            'questions_with_registered_source' => count($registeredSourceQuestionIds),
            'questions_requiring_explanation' => count($effective) - count($explanationsAvailable),
            'instructional_coverage_classification' => count($conceptInstruction) === 0 ? 'NO_INSTRUCTION' : (count($conceptInstruction) < count($conceptsPresent) ? 'PARTIAL_INSTRUCTION' : 'STRUCTURALLY_CONCEPT_MAPPED'),
            'diagnostics' => $diagnostics,
        ];
    }
}

usort($rows, static fn(array $a, array $b): int => strcmp($a['board_id'] . '|' . $a['subject_id'], $b['board_id'] . '|' . $b['subject_id']));
$zeroLessons = count(array_filter($rows, static fn(array $row): bool => $row['existing_lesson_count'] === 0));
$sourceOnlyPlacements = array_sum(array_column($rows, 'source_only_questions'));
$canonicalCount = count($questions);
$payload = [
    'generated_at' => gmdate(DATE_ATOM),
    'baseline_head' => trim((string) shell_exec('git rev-parse HEAD')),
    'count_semantics' => 'Effective question counts use production BoardViewService selectors and QuestionEligibilityService with production derived reuse relationships. Placement counts are board-subject placements, not unique question IDs. A real-instruction concept is a structural heuristic requiring an explicit concept association and substantive body text; it is not semantic academic approval. Verified source coverage counts registered question sources whose registry status is verified, not independent verification of question-source relevance.',
    'totals' => [
        'boards' => count($activeBoardIds),
        'active_subject_selectors' => count($rows),
        'canonical_questions' => $canonicalCount,
        'zero_lesson_subjects' => $zeroLessons,
        'source_only_subject_question_placements' => $sourceOnlyPlacements,
        'lesson_linked_subject_question_placements' => array_sum(array_column($rows, 'lesson_linked_questions')),
        'published_study_pdfs' => count((array) ($manifest['files'] ?? [])),
        'concepts_with_real_instruction' => array_sum(array_column($rows, 'concepts_with_real_instruction')),
        'concepts_without_instruction' => array_sum(array_column($rows, 'concepts_without_instruction')),
        'broken_lesson_links' => array_sum(array_column($rows, 'broken_lesson_links')),
        'semantic_link_review_candidates' => array_sum(array_column($rows, 'semantic_link_review_candidates')),
    ],
    'subjects' => $rows,
];

$zeroRows = array_values(array_filter($rows, static fn(array $row): bool => $row['existing_lesson_count'] === 0));
if (count($rows) !== 43 || count($activeBoardIds) !== 10
    || (!empty($options['authoritative_baseline'])
        && ($canonicalCount !== 9123 || $zeroLessons !== 25 || $sourceOnlyPlacements !== 8121))) {
    fwrite(STDERR, 'Baseline reconciliation failed: ' . json_encode($payload['totals'], JSON_UNESCAPED_SLASHES) . "\n");
    exit(1);
}

$json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
$lines = [
    '# Batch 696 Instructional Coverage Inventory', '',
    'Generated: ' . $payload['generated_at'], '',
    'Baseline commit: `' . $payload['baseline_head'] . '`.', '',
    sprintf('Boards: **%d** · active subject selectors: **%d** · canonical questions: **%d** · zero-lesson subjects: **%d** · source-only placements: **%d** · published PDFs: **%d**.', $payload['totals']['boards'], $payload['totals']['active_subject_selectors'], $canonicalCount, $zeroLessons, $sourceOnlyPlacements, $payload['totals']['published_study_pdfs']), '',
    'Counts use production learner selectors and `QuestionEligibilityService`. Placements may repeat one canonical question across supported boards. `concepts_with_real_instruction` is only a structural signal (explicit concept association plus substantive text); it is not academic approval. Registry-verified source coverage does not establish source/question semantic relevance.', '',
    '| Board | Subject | Effective Q | Topics | Concepts | Lessons / sections | Substantive sections | PDFs | Lesson linked | Source only | Broken links | Semantic review candidates | Concepts instructed / total | Worked examples | Explanations | Verified-source Q |',
    '|---|---|---:|---:|---:|---:|---:|---:|---:|---:|---:|---:|---:|---:|---:|---:|',
];
foreach ($rows as $row) {
    $lines[] = sprintf('| %s (`%s`) | %s (`%s`) | %d | %d | %d | %d / %d | %d | %d | %d | %d | %d | %d | %d / %d | %d | %d / %d | %d |',
        $row['board_name'], $row['board_id'], $row['subject_name'], $row['subject_id'], $row['effective_question_count'], $row['topic_count'], $row['concept_count'], $row['existing_lesson_count'], $row['existing_lesson_sections'], $row['substantive_lesson_sections'], $row['downloadable_pdfs'], $row['lesson_linked_questions'], $row['source_only_questions'], $row['broken_lesson_links'], $row['semantic_link_review_candidates'], $row['concepts_with_real_instruction'], $row['concept_count'], $row['worked_examples_available'], $row['practice_explanations_available'], $row['effective_question_count'], $row['verified_source_coverage']);
}
$lines[] = '';
$lines[] = '## Zero-lesson subject inventory';
$lines[] = '';
foreach ($zeroRows as $row) $lines[] = '- ' . $row['board_name'] . ' — ' . $row['subject_name'] . ' (`' . $row['subject_id'] . '`, ' . $row['effective_question_count'] . ' effective questions, ' . $row['concept_count'] . ' concepts).';
$lines[] = '';
$lines[] = '## Semantic and verification limits';
$lines[] = '';
$lines[] = '- Topic/concept tags are inventory aids. They do not prove that a lesson section teaches a question or that a source supports its answer.';
$lines[] = '- A question without a valid same-board/same-subject published lesson remains source-only. Links with no explicit section mapping are semantic review candidates.';
$lines[] = '- Source verification reflects the source registry status. A registered source must still be read and checked for the specific proposition before it supports new instructional claims.';
$lines[] = '- A structurally substantive section is not an independently reviewed lesson.';
$markdown = implode("\n", $lines) . "\n";

foreach (['json' => $json, 'markdown' => $markdown] as $kind => $contents) {
    $path = $options[$kind];
    $directory = dirname($path);
    if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) throw new RuntimeException('Cannot create report directory: ' . $directory);
    $temporary = $path . '.tmp-' . bin2hex(random_bytes(4));
    if (file_put_contents($temporary, $contents, LOCK_EX) !== strlen($contents) || !rename($temporary, $path)) {
        @unlink($temporary);
        throw new RuntimeException('Unable to atomically write coverage report: ' . $path);
    }
}
printf("STATUS=PASS BOARDS=%d SUBJECTS=%d QUESTIONS=%d ZERO_LESSON_SUBJECTS=%d SOURCE_ONLY_PLACEMENTS=%d\nJSON=%s\nMARKDOWN=%s\n", $payload['totals']['boards'], count($rows), $canonicalCount, $zeroLessons, $sourceOnlyPlacements, $options['json'], $options['markdown']);
