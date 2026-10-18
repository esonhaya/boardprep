#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

$args = array_slice($argv, 1);
$jsonPath = null;
$markdownPath = null;
foreach ($args as $arg) {
    if (str_starts_with($arg, '--json=')) {
        $jsonPath = substr($arg, 7);
    } elseif (str_starts_with($arg, '--markdown=')) {
        $markdownPath = substr($arg, 11);
    } elseif ($arg === '--help') {
        fwrite(STDOUT, "Usage: php tools/generate-subject-depth-inventory.php --json=PATH --markdown=PATH\n");
        exit(0);
    } else {
        fwrite(STDERR, "Unknown option: {$arg}\n");
        exit(2);
    }
}
if ($jsonPath === null || $markdownPath === null || $jsonPath === '' || $markdownPath === '') {
    fwrite(STDERR, "Both --json and --markdown output paths are required.\n");
    exit(2);
}

putenv('APP_ENV=testing');
putenv('APP_DEBUG=false');
\App\Core\App::boot();
$storage = \App\Core\App::storage();
$boards = $storage->all('boards');
$subjects = $storage->all('subjects');
$practiceBoards = \App\Services\Board\BoardViewService::all();
$questions = $storage->all('questions');
$blueprints = $storage->all('blueprints');
$eligibility = new \App\Services\Question\QuestionEligibilityIndex(
    $storage->all('question-eligibility'),
    $blueprints,
    \App\Services\Question\QuestionReuseEligibilityResolver::productionDerivedRelationships(),
    $storage->all('question-reuse-overrides')
);

$boardById = [];
foreach ($boards as $board) {
    if (is_array($board)) $boardById[strtolower((string) ($board['id'] ?? ''))] = $board;
}
$practiceScopes = [];
foreach ($practiceBoards as $practiceBoard) {
    if (!is_array($practiceBoard)) continue;
    $boardId = strtolower((string) ($practiceBoard['id'] ?? ''));
    foreach ((array) ($practiceBoard['practice_subjects'] ?? []) as $practiceSubject) {
        if (!is_array($practiceSubject)) continue;
        $subjectId = strtolower((string) ($practiceSubject['id'] ?? ''));
        if ($boardId !== '' && $subjectId !== '') {
            $practiceScopes[$boardId][$subjectId] = [
                'name' => (string) ($practiceSubject['name'] ?? $subjectId),
                'selector_count' => (int) ($practiceSubject['available_questions'] ?? 0),
            ];
        }
    }
}
$relationScopes = [];
foreach ($storage->all('board-subjects') as $relation) {
    if (is_array($relation)) $relationScopes[strtolower((string) ($relation['board_id'] ?? '')) . '|' . strtolower((string) ($relation['subject_id'] ?? ''))] = true;
}
$subjectById = [];
foreach ($subjects as $subject) {
    if (is_array($subject)) $subjectById[(string) ($subject['id'] ?? '')] = $subject;
}
$materialsById = [];
foreach ($storage->all('study-materials') as $material) {
    if (is_array($material)) $materialsById[(string) ($material['id'] ?? '')] = $material;
}
$publishedLessonsByMaterial = [];
$lessonsByScope = [];
foreach ($storage->all('study-lessons') as $lesson) {
    if (!is_array($lesson) || ($lesson['status'] ?? '') !== 'published') continue;
    $materialId = (string) ($lesson['material_id'] ?? '');
    if ($materialId !== '') $publishedLessonsByMaterial[$materialId] = $lesson;
    $scope = strtolower((string) ($lesson['board_id'] ?? '')) . '|' . strtolower((string) ($lesson['subject_id'] ?? ''));
    $lessonsByScope[$scope][] = $lesson;
}
$linksByQuestion = [];
foreach ($storage->all('study-question-links') as $link) {
    if (is_array($link)) $linksByQuestion[(string) ($link['question_id'] ?? '')][] = $link;
}

$rows = [];
foreach ($practiceScopes as $boardId => $boardPracticeSubjects) {
  foreach ($boardPracticeSubjects as $subjectId => $practiceSubject) {
    $board = $boardById[$boardId] ?? null;
    $subject = $subjectById[$subjectId] ?? ['id' => $subjectId, 'name' => $practiceSubject['name']];
    if (!is_array($board) || !is_array($subject)) continue;
    $boardStatus = strtolower((string) ($board['status'] ?? $board['active'] ?? 'active'));
    $subjectStatus = strtolower((string) ($subject['status'] ?? $subject['active'] ?? 'active'));
    if (in_array($boardStatus, ['inactive', 'archived', 'disabled'], true)
        || in_array($subjectStatus, ['inactive', 'archived', 'disabled'], true)) continue;

    $effective = [];
    $canonical = [];
    foreach ($questions as $question) {
        if (!is_array($question) || !is_scalar($question['id'] ?? null)) continue;
        if (strtolower((string) ($question['status'] ?? 'active')) === 'archived') continue;
        $id = (string) $question['id'];
        $taxonomy = is_array($question['taxonomy'] ?? null) ? $question['taxonomy'] : [];
        $canonicalBoard = strtolower((string) ($taxonomy['board_id'] ?? $question['board'] ?? ''));
        $canonicalSubject = strtolower((string) ($taxonomy['subject_id'] ?? $question['subject'] ?? $question['subject_id'] ?? ''));
        if ($canonicalBoard === $boardId && $canonicalSubject === $subjectId) $canonical[$id] = $question;
        $resolved = \App\Services\Question\QuestionEligibilityService::forExam($question, $boardId, $eligibility);
        if ($resolved === null && ($board['code'] ?? '') !== '') {
            $resolved = \App\Services\Question\QuestionEligibilityService::forExam($question, (string) $board['code'], $eligibility);
        }
        if (($resolved['subject_id'] ?? '') === $subjectId) $effective[$id] = $question;
    }

    $topics = $concepts = $difficulties = [];
    $lessonLinkedQuestions = [];
    $sourceOnly = 0;
    $reused = 0;
    $duplicateIds = [];
    $stems = [];
    foreach ($effective as $id => $question) {
        $taxonomy = is_array($question['taxonomy'] ?? null) ? $question['taxonomy'] : [];
        $topic = (string) ($taxonomy['topic_id'] ?? $question['topic'] ?? 'unclassified');
        $concept = (string) ($taxonomy['concept_id'] ?? $question['concept'] ?? 'unclassified');
        $difficulty = strtolower((string) ($question['difficulty'] ?? 'unclassified'));
        $topics[$topic] = ($topics[$topic] ?? 0) + 1;
        $concepts[$concept] = ($concepts[$concept] ?? 0) + 1;
        $difficulties[$difficulty] = ($difficulties[$difficulty] ?? 0) + 1;
        $canonicalBoard = strtolower((string) ($taxonomy['board_id'] ?? $question['board'] ?? ''));
        if ($canonicalBoard !== $boardId) $reused++;
        $stem = strtolower(preg_replace('/[^\pL\pN]+/u', ' ', trim((string) ($question['question'] ?? ''))) ?? '');
        if ($stem !== '') {
            if (isset($stems[$stem])) $duplicateIds[$id] = true;
            else $stems[$stem] = $id;
        }
        $foundLesson = false;
        foreach ($linksByQuestion[$id] ?? [] as $link) {
            $materialId = (string) ($link['material_id'] ?? '');
            $material = $materialsById[$materialId] ?? null;
            $lesson = $publishedLessonsByMaterial[$materialId] ?? null;
            if (is_array($material) && is_array($lesson)
                && strtolower((string) ($lesson['board_id'] ?? '')) === $boardId
                && strtolower((string) ($lesson['subject_id'] ?? '')) === $subjectId) {
                $lessonLinkedQuestions[$id] = true;
                $foundLesson = true;
            }
        }
        foreach ($lessonsByScope[$boardId . '|' . $subjectId] ?? [] as $lesson) {
            if (in_array((string) $id, array_map('strval', (array) ($lesson['practice_question_ids'] ?? [])), true)) {
                $lessonLinkedQuestions[$id] = true;
                $foundLesson = true;
            }
        }
        if (!$foundLesson && ($linksByQuestion[$id] ?? []) !== []) $sourceOnly++;
    }

    $scopeLessons = $lessonsByScope[$boardId . '|' . $subjectId] ?? [];
    $scopeMaterials = [];
    $downloadable = 0;
    foreach ($materialsById as $materialId => $material) {
        $taxonomy = is_array($material['taxonomy'] ?? null) ? $material['taxonomy'] : [];
        $hasBoard = in_array($boardId, array_map('strtolower', array_map('strval', $material['exams'] ?? [])), true);
        $hasSubject = strtolower((string) ($taxonomy['subject_id'] ?? '')) === $subjectId;
        if (!$hasBoard || !$hasSubject || ($material['status'] ?? 'published') !== 'published') continue;
        $scopeMaterials[$materialId] = true;
        $lesson = $publishedLessonsByMaterial[$materialId] ?? null;
        $filename = is_array($lesson) ? (string) ($lesson['pdf_file'] ?? '') : '';
        if (preg_match('/\A[a-z0-9][a-z0-9-]*\.pdf\z/', $filename) === 1) {
            $path = dirname(__DIR__) . '/public/study-resources/' . $filename;
            if (is_file($path) && filesize($path) >= 8 && file_get_contents($path, false, null, 0, 5) === '%PDF-') $downloadable++;
        }
    }

    $blueprintNodes = [];
    foreach ($blueprints as $blueprint) {
        if (!is_array($blueprint) || strtolower((string) ($blueprint['board_id'] ?? '')) !== $boardId) continue;
        foreach ((array) ($blueprint['sections'] ?? []) as $section) {
            if (!is_array($section)) continue;
            $ids = array_map('strtolower', array_map('strval', (array) ($section['subjects'] ?? [$section['id'] ?? ''])));
            if (in_array($subjectId, $ids, true) || strtolower((string) ($section['id'] ?? '')) === $subjectId) {
                $blueprintNodes[] = (string) ($section['id'] ?? '');
            }
        }
    }
    $count = count($effective);
    $rows[] = [
        'board_id' => $boardId,
        'board_name' => (string) ($board['name'] ?? $boardId),
        'subject_id' => $subjectId,
        'subject_name' => (string) ($subject['name'] ?? $practiceSubject['name'] ?? $subjectId),
        'active_relation' => isset($relationScopes[$boardId . '|' . $subjectId]),
        'learner_selectable' => true,
        'selector_question_count' => $practiceSubject['selector_count'],
        'blueprint_nodes' => array_values(array_unique($blueprintNodes)),
        'canonical_question_count' => count($canonical),
        'effective_eligible_question_count' => $count,
        'unique_question_ids' => $count,
        'unique_concept_count' => count($concepts),
        'concept_distribution' => (object) $concepts,
        'topic_distribution' => (object) $topics,
        'difficulty_distribution' => (object) $difficulties,
        'existing_lesson_count' => count($scopeLessons),
        'downloadable_study_material_count' => $downloadable,
        'questions_with_valid_lesson_links' => count($lessonLinkedQuestions),
        'questions_with_source_only_links' => $sourceOnly,
        'duplicate_question_content_count' => count($duplicateIds),
        'reused_question_count' => $reused,
        'number_required_to_reach_100' => max(0, 100 - $count),
    ];
  }
}

usort($rows, static fn(array $a, array $b): int => ($b['number_required_to_reach_100'] <=> $a['number_required_to_reach_100'])
    ?: strcmp($a['board_id'] . '|' . $a['subject_id'], $b['board_id'] . '|' . $b['subject_id']));
$deficit = array_sum(array_column($rows, 'number_required_to_reach_100'));
$below = count(array_filter($rows, static fn(array $row): bool => $row['number_required_to_reach_100'] > 0));
$payload = [
    'generated_at' => gmdate(DATE_ATOM),
    'target_effective_questions_per_active_subject' => 100,
    'count_semantics' => 'Unique active question IDs returned by the production QuestionEligibilityService for each active BoardViewService learner-facing practice subject. Archived questions are excluded. This avoids counting stale configured relation IDs that do not resolve into learner subject selectors. active_relation indicates whether a matching board-subject relation exists.',
    'totals' => [
        'boards_audited' => count(array_filter($boards, static fn($b): bool => is_array($b) && !in_array(strtolower((string) ($b['status'] ?? $b['active'] ?? 'active')), ['inactive', 'archived', 'disabled'], true))),
    'active_board_subjects' => count($rows),
        'subjects_below_100' => $below,
        'total_effective_question_deficit' => $deficit,
    ],
    'subjects' => $rows,
];
$json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
$directory = dirname($jsonPath);
if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) throw new RuntimeException('Cannot create JSON output directory.');
if (file_put_contents($jsonPath, $json, LOCK_EX) === false) throw new RuntimeException('Cannot write JSON inventory.');
$lines = [
    '# Batch 694 Subject Depth Inventory',
    '',
    'Generated: ' . $payload['generated_at'],
    '',
    'Count = unique, non-archived question IDs resolved by the production board eligibility service into each active subject shown in the learner-facing practice selector. This excludes stale relation IDs that do not resolve into learner practice. The JSON also records whether each selector subject has a matching board-subject relation and blueprint node.',
    '',
    sprintf('Boards audited: **%d**; active board-subject combinations: **%d**; below 100: **%d**; total deficit: **%d** questions.', $payload['totals']['boards_audited'], count($rows), $below, $deficit),
    '',
    '| Board | Subject | Canonical | Effective eligible | Concepts | Topics | Lessons | PDFs | Questions lesson-linked | Source-only | Reused | Duplicate content | To 100 | Relation |',
    '|---|---|---:|---:|---:|---:|---:|---:|---:|---:|---:|---:|---:|---|',
];
foreach ($rows as $row) {
    $topicSummary = [];
    foreach ((array) $row['topic_distribution'] as $topic => $amount) $topicSummary[] = $topic . ':' . $amount;
    $lines[] = sprintf('| %s | %s (`%s`) | %d | %d | %d | %s | %d | %d | %d | %d | %d | %d | %d | %s |',
        $row['board_name'], $row['subject_name'], $row['subject_id'], $row['canonical_question_count'],
        $row['effective_eligible_question_count'], $row['unique_concept_count'], $topicSummary === [] ? '—' : implode(', ', $topicSummary),
        $row['existing_lesson_count'], $row['downloadable_study_material_count'], $row['questions_with_valid_lesson_links'],
        $row['questions_with_source_only_links'], $row['reused_question_count'], $row['duplicate_question_content_count'],
        $row['number_required_to_reach_100'], $row['active_relation'] ? 'yes' : 'no');
}
$lines[] = '';
$lines[] = 'Difficulty distribution and exact topic counts are available in the JSON record for each combination.';
$md = implode("\n", $lines) . "\n";
$directory = dirname($markdownPath);
if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) throw new RuntimeException('Cannot create Markdown output directory.');
if (file_put_contents($markdownPath, $md, LOCK_EX) === false) throw new RuntimeException('Cannot write Markdown inventory.');
printf("SUBJECTS=%d BELOW_100=%d DEFICIT=%d\nJSON=%s\nMARKDOWN=%s\n", count($rows), $below, $deficit, $jsonPath, $markdownPath);
