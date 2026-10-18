#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();
putenv('APP_ENV=testing');
putenv('APP_DEBUG=false');
\App\Core\App::boot();

use App\Core\App;
use App\Services\Question\QuestionEligibilityIndex;
use App\Services\Question\QuestionEligibilityService;
use App\Services\Question\QuestionReuseEligibilityResolver;
use App\Services\RepositoryHealth\Readiness\SourceReadinessRecord;

const B695_EXPECTED_HEAD = 'f657b89629a0262d3ebca0ce99baaf551268acf8';
const B695_BASELINE_PATH = '/data/data/com.termux/files/home/.cache/batch695-recovery/question-baseline.json';

function b695_fail(string $message): never
{
    fwrite(STDERR, "BATCH695_FAIL={$message}\n");
    exit(1);
}

function b695_json(string $path): array
{
    if (!is_file($path)) b695_fail("Missing JSON file: {$path}");
    try {
        $data = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    } catch (Throwable $exception) {
        b695_fail("Invalid JSON in {$path}: " . $exception->getMessage());
    }
    if (!is_array($data)) b695_fail("Expected JSON array/object in {$path}.");
    return $data;
}

function b695_json_payload(array $data): string
{
    return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
}

function b695_normalize(string $value): string
{
    return mb_strtolower(preg_replace('/\s+/u', ' ', trim($value)) ?? trim($value));
}

function b695_atomic_write(string $path, string $payload): void
{
    $temporary = $path . '.batch695-tmp';
    if (is_file($temporary)) b695_fail("Refusing to replace pre-existing temporary file {$temporary}; inspect it first.");
    $permissions = fileperms($path);
    $mode = $permissions === false ? 0644 : ($permissions & 0777);
    $written = file_put_contents($temporary, $payload, LOCK_EX);
    if ($written !== strlen($payload)) {
        @unlink($temporary);
        b695_fail("Short write while preparing {$path}.");
    }
    @chmod($temporary, $mode);
    if (!rename($temporary, $path)) {
        @unlink($temporary);
        b695_fail("Atomic rename failed for {$path}.");
    }
}

$mode = $argv[1] ?? '';
if (!in_array($mode, ['--dry-run', '--apply'], true)) {
    fwrite(STDERR, "Usage: php tools/apply-batch695.php --dry-run|--apply\n");
    exit(2);
}

$root = dirname(__DIR__);
$head = trim((string) shell_exec('git rev-parse HEAD'));
$remoteHead = trim((string) shell_exec('git rev-parse origin/main'));
if ($head !== B695_EXPECTED_HEAD || $remoteHead !== B695_EXPECTED_HEAD) {
    b695_fail('HEAD/origin changed from the verified Batch 694 baseline; reconcile before applying.');
}
if (!is_file(B695_BASELINE_PATH)) b695_fail('Private per-question baseline hash file is missing.');
$baseline = b695_json(B695_BASELINE_PATH);

$evidence = require __DIR__ . '/batch695-evidence-content.php';
$questionFiles = glob(__DIR__ . '/batch695-question-data-*.php') ?: [];
sort($questionFiles, SORT_STRING);
$questionData = [];
foreach ($questionFiles as $file) {
    foreach ((array) require $file as $conceptId => $rows) {
        if (isset($questionData[$conceptId])) b695_fail("Duplicate concept in question data: {$conceptId}");
        $questionData[$conceptId] = $rows;
    }
}

$conceptsData = [];
foreach ((array) ($evidence['concepts'] ?? []) as $row) {
    if (!is_array($row) || !is_string($row['id'] ?? null)) b695_fail('Malformed concept definition.');
    $conceptsData[$row['id']] = $row;
}
$topicsData = [];
foreach ((array) ($evidence['topics'] ?? []) as $row) {
    if (!is_array($row) || !is_string($row['id'] ?? null)) b695_fail('Malformed topic definition.');
    $topicsData[$row['id']] = $row;
}
$conceptSources = (array) ($evidence['concept_sources'] ?? []);
$conceptSections = (array) ($evidence['concept_sections'] ?? []);

$subjectConfig = [
    'early-childhood-education'=>['board'=>'let','specialization'=>'elementary-early-childhood-education','level'=>'ELEMENTARY','material'=>'let-early-childhood-learning-and-inclusion'],
    'special-needs-education'=>['board'=>'let','specialization'=>'elementary-special-needs-education','level'=>'ELEMENTARY','material'=>'let-special-needs-inclusive-education'],
    'technical-vocational-teacher-education'=>['board'=>'let','specialization'=>'secondary-technical-vocational-teacher-education','level'=>'SECONDARY','material'=>'let-tvted-competency-based-teaching'],
    'culture-and-arts-education'=>['board'=>'let','specialization'=>'secondary-culture-and-arts-education','level'=>'SECONDARY','material'=>'let-culture-arts-learning-foundation'],
    'physical-education'=>['board'=>'let','specialization'=>'secondary-physical-education','level'=>'SECONDARY','material'=>'let-physical-education-movement-and-safety'],
    'architecture-utilities-and-construction'=>['board'=>'architecture','specialization'=>null,'level'=>null,'material'=>'architecture-building-systems-and-construction'],
];
$targetQuestions = [
    'early-childhood-education'=>58,
    'special-needs-education'=>58,
    'technical-vocational-teacher-education'=>52,
    'culture-and-arts-education'=>52,
    'physical-education'=>52,
    'architecture-utilities-and-construction'=>4,
];

$beforeQuestions = b695_json($root . '/storage/questions.json');
$beforeCount = count($beforeQuestions);
if ($beforeCount !== 9123) b695_fail("Canonical count changed from baseline: {$beforeCount}");
$currentHashById = [];
$currentQuestionIds = [];
$maxId = 0;
foreach ($beforeQuestions as $question) {
    if (!is_array($question) || !is_numeric($question['id'] ?? null)) b695_fail('Question bank contains a non-numeric or malformed record.');
    $id = (string) $question['id'];
    if (isset($currentQuestionIds[$id])) b695_fail("Duplicate existing question identity {$id}.");
    $currentQuestionIds[$id] = true;
    $maxId = max($maxId, (int) $id);
    $currentHashById[$id] = hash('sha256', json_encode($question, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR));
}
if ((int) ($baseline['count'] ?? -1) !== count($currentHashById)) b695_fail('Question-bank identity count no longer matches the protected baseline.');
foreach ((array) ($baseline['hashes'] ?? []) as $id => $hash) {
    if (!isset($currentHashById[(string) $id]) || !hash_equals((string) $hash, $currentHashById[(string) $id])) {
        b695_fail("A pre-existing question record changed during Batch 695 authoring: {$id}");
    }
}
if (array_diff_key($currentHashById, (array) ($baseline['hashes'] ?? [])) !== []) b695_fail('Question bank contains an identity outside the protected baseline before authoring.');
foreach ($beforeQuestions as $question) if (($question['batch_marker'] ?? '') === 'batch695') b695_fail('Batch 695 questions already exist; inspect partial prior work before retrying.');

$sources = b695_json($root . '/storage/sources.json');
$sourceById = [];
foreach ($sources as $source) if (is_array($source)) $sourceById[(string) ($source['id'] ?? '')] = $source;
foreach ((array) ($evidence['sources'] ?? []) as $source) {
    $id = (string) ($source['id'] ?? '');
    if ($id === '' || isset($sourceById[$id])) b695_fail("Source ID is empty or already registered: {$id}");
    $sources[] = $source;
    $sourceById[$id] = $source;
}

$topics = b695_json($root . '/storage/taxonomy/topics.json');
$domainRows = b695_json($root . '/storage/taxonomy/domains.json');
$domainById = [];
foreach ($domainRows as $domain) if (is_array($domain)) $domainById[(string) ($domain['id'] ?? '')] = $domain;
$topicById = [];
foreach ($topics as $topic) if (is_array($topic)) $topicById[(string) ($topic['id'] ?? '')] = $topic;
foreach ($topicsData as $id => $topic) {
    if (isset($topicById[$id])) b695_fail("New topic collides with an existing taxonomy ID: {$id}");
    $domainId = (string) ($topic['domain_id'] ?? '');
    $domain = $domainById[$domainId] ?? null;
    if (!is_array($domain)) b695_fail("Topic {$id} refers to an unknown domain {$domainId}.");
    $subjectId = (string) ($domain['subject_id'] ?? '');
    if (!isset($subjectConfig[$subjectId]) && $subjectId !== 'architecture-utilities-and-construction') b695_fail("Topic {$id} refers to an out-of-scope subject {$subjectId}.");
    $topic['learning'] = [
        'difficulty'=>'medium',
        'estimated_study_minutes'=>45,
        'mastery_threshold'=>80,
        'prerequisite_ids'=>[],
        'learning_objectives'=>['Explain the controlling principle.','Apply the principle to a new professional or classroom scenario.'],
    ];
    $topics[] = $topic;
    $topicById[$id] = $topic;
}

$concepts = b695_json($root . '/storage/taxonomy/concepts.json');
$conceptById = [];
foreach ($concepts as $concept) if (is_array($concept)) $conceptById[(string) ($concept['id'] ?? '')] = $concept;
foreach ($conceptsData as $id => $concept) {
    if (isset($conceptById[$id])) b695_fail("New concept collides with an existing taxonomy ID: {$id}");
    $topicId = (string) ($concept['topic_id'] ?? '');
    if (!isset($topicById[$topicId])) b695_fail("Concept {$id} refers to an unknown topic {$topicId}.");
    $concept['learning'] = [
        'difficulty'=>'medium',
        'estimated_study_minutes'=>30,
        'mastery_threshold'=>80,
        'keywords'=>[$concept['name']],
        'common_misconceptions'=>[],
    ];
    $concepts[] = $concept;
    $conceptById[$id] = $concept;
}

if (count($questionData) !== count($conceptsData) || count($questionData) !== 51) b695_fail('Question bank and new concept inventory do not align.');
$questionCountBySubject = array_fill_keys(array_keys($subjectConfig), 0);
$questionCountByConcept = [];
$stemSet = [];
$nextId = $maxId + 1;
$timestamp = gmdate('Y-m-d\TH:i:s+00:00');
$eligibility = b695_json($root . '/storage/question-eligibility.json');
$links = b695_json($root . '/storage/study-question-links.json');
$coverage = b695_json($root . '/storage/board-concept-coverage.json');
$lessons = b695_json($root . '/storage/study-lessons.json');
$materials = b695_json($root . '/storage/study-materials.json');
$lessonIndex = [];
foreach ($lessons as $i => $lesson) if (is_array($lesson)) $lessonIndex[(string) ($lesson['material_id'] ?? '')] = $i;
$materialIndex = [];
foreach ($materials as $i => $material) if (is_array($material)) $materialIndex[(string) ($material['id'] ?? '')] = $i;

$updatedLessonIds = [];
$sectionCrosswalk = [];
$linksByQuestion = [];
foreach ($links as $link) {
    if (!is_array($link)) continue;
    $linksByQuestion[(string) ($link['question_id'] ?? '')][] = $link;
}
$addSectionCrosswalk = static function (string $materialId, string $sectionId, string $topicId, string $conceptId) use (&$sectionCrosswalk): void {
    if ($materialId === '' || $sectionId === '') return;
    if ($topicId !== '') $sectionCrosswalk[$materialId][$sectionId]['topics'][$topicId] = true;
    if ($conceptId !== '') $sectionCrosswalk[$materialId][$sectionId]['concepts'][$conceptId] = true;
};
foreach ($beforeQuestions as $question) {
    if (!is_array($question)) continue;
    $taxonomy = is_array($question['taxonomy'] ?? null) ? $question['taxonomy'] : [];
    $destinations = [];
    $materialId = (string) ($question['study_material_id'] ?? '');
    $sectionId = (string) ($question['study_section_id'] ?? '');
    if ($materialId !== '' && $sectionId !== '') $destinations[] = [$materialId, $sectionId];
    foreach ($linksByQuestion[(string) ($question['id'] ?? '')] ?? [] as $link) {
        $linkedMaterial = (string) ($link['material_id'] ?? '');
        $linkedSection = (string) ($link['section_id'] ?? '');
        if ($linkedMaterial !== '' && $linkedSection !== '') $destinations[] = [$linkedMaterial, $linkedSection];
    }
    foreach ($destinations as [$materialId, $sectionId]) {
        $addSectionCrosswalk($materialId, $sectionId, (string) ($taxonomy['topic_id'] ?? ''), (string) ($taxonomy['concept_id'] ?? ''));
    }
}
foreach ($conceptSections as $conceptId => $sectionId) {
    $concept = $conceptsData[$conceptId] ?? null;
    if (!is_array($concept)) b695_fail("Section crosswalk refers to an unknown concept {$conceptId}.");
    $topicId = (string) ($concept['topic_id'] ?? '');
    $topic = $topicById[$topicId] ?? null;
    $domain = is_array($topic) ? ($domainById[(string) ($topic['domain_id'] ?? '')] ?? null) : null;
    $subjectId = is_array($domain) ? (string) ($domain['subject_id'] ?? '') : '';
    $materialId = (string) ($subjectConfig[$subjectId]['material'] ?? '');
    if ($materialId === '') b695_fail("Section crosswalk has no material mapping for {$conceptId}.");
    $addSectionCrosswalk($materialId, (string) $sectionId, $topicId, (string) $conceptId);
}
foreach ((array) ($evidence['lessons'] ?? []) as $materialId => $update) {
    if (!isset($lessonIndex[$materialId], $materialIndex[$materialId])) b695_fail("Published lesson/material pair is missing: {$materialId}");
    $lessonPosition = $lessonIndex[$materialId];
    $lesson = $lessons[$lessonPosition];
    if (($lesson['status'] ?? '') !== 'published') b695_fail("Lesson is not published: {$materialId}");
    $existingSectionIds = array_map(static fn(array $section): string => (string) ($section['id'] ?? ''), (array) ($lesson['sections'] ?? []));
    $newSectionIds = array_map(static fn(array $section): string => (string) ($section['id'] ?? ''), (array) ($update['sections'] ?? []));
    if (count($newSectionIds) !== count(array_unique($newSectionIds))) b695_fail("Lesson contains duplicate section IDs: {$materialId}");
    foreach ($existingSectionIds as $sectionId) if (!in_array($sectionId, $newSectionIds, true)) b695_fail("Lesson update would remove an existing section: {$materialId}/{$sectionId}");
    foreach ($update['sections'] as &$section) {
        $sectionId = (string) ($section['id'] ?? '');
        $crosswalk = $sectionCrosswalk[$materialId][$sectionId] ?? [];
        $section['related_topic_ids'] = array_values(array_unique(array_merge(
            array_map('strval', (array) ($section['related_topic_ids'] ?? [])),
            array_keys((array) ($crosswalk['topics'] ?? []))
        )));
        $section['related_concept_ids'] = array_values(array_unique(array_merge(
            array_map('strval', (array) ($section['related_concept_ids'] ?? [])),
            array_keys((array) ($crosswalk['concepts'] ?? []))
        )));
    }
    unset($section);
    foreach (['learning_objectives','sections','worked_examples','common_misconceptions','question_patterns','official_scope_source_ids','instructional_source_ids'] as $field) {
        if (!is_array($update[$field] ?? null)) b695_fail("Lesson update lacks {$field}: {$materialId}");
        $lesson[$field] = $update[$field];
    }
    foreach (array_merge((array) $lesson['official_scope_source_ids'], (array) $lesson['instructional_source_ids']) as $sourceId) {
        if (!isset($sourceById[(string) $sourceId])) b695_fail("Lesson {$materialId} references missing source {$sourceId}.");
    }
    $lesson['version'] = (int) ($lesson['version'] ?? 1) + 1;
    $lesson['source_review'] = [
        'status'=>'SOURCE_CHECKED_HUMAN_REVIEW_PENDING',
        'checked_at'=>'2026-10-18',
        'note'=>'Official examination scope and instructional source text were inspected and used to write original lesson content. Independent academic review and owner approval remain pending; no external source text is reproduced.',
    ];
    $lessons[$lessonPosition] = $lesson;
    $updatedLessonIds[$materialId] = true;
}

$storage = App::storage();
$templateIndex = QuestionEligibilityService::index();
$templateBySubject = [];
$placementBySubject = [];
foreach ($subjectConfig as $subjectId => $config) {
    $boardId = $config['board'];
    foreach ($beforeQuestions as $candidate) {
        if (!is_array($candidate)) continue;
        $taxonomy = is_array($candidate['taxonomy'] ?? null) ? $candidate['taxonomy'] : [];
        if ((string) ($taxonomy['board_id'] ?? $candidate['board'] ?? '') !== $boardId || (string) ($taxonomy['subject_id'] ?? $candidate['subject_id'] ?? '') !== $subjectId) continue;
        $resolved = QuestionEligibilityService::forExam($candidate, $boardId, $templateIndex);
        if (!is_array($resolved) || (string) ($resolved['subject_id'] ?? '') !== $subjectId || !is_array($resolved['placement'] ?? null)) continue;
        $templateBySubject[$subjectId] = $candidate;
        $placementBySubject[$subjectId] = $resolved['placement'];
        break;
    }
    if (!isset($templateBySubject[$subjectId], $placementBySubject[$subjectId])) b695_fail("No production-eligible template found for {$boardId}/{$subjectId}.");
}

$existingEligibilityIds = [];
foreach ($eligibility as $i => $entry) if (is_array($entry)) $existingEligibilityIds[(string) ($entry['id'] ?? '')] = $i;
$batchEntries = [];
$pendingQuestions = [];
$pendingMaps = [];
$answerPositions = array_fill(0, 4, 0);
$globalIndex = 0;

foreach ($questionData as $conceptId => $rows) {
    $concept = $conceptsData[$conceptId] ?? null;
    if (!is_array($concept)) b695_fail("Question concept does not resolve: {$conceptId}");
    $topic = $topicById[(string) ($concept['topic_id'] ?? '')] ?? null;
    if (!is_array($topic)) b695_fail("Topic does not resolve for {$conceptId}.");
    $domainId = (string) ($topic['domain_id'] ?? '');
    $domain = $domainById[$domainId] ?? null;
    if (!is_array($domain)) b695_fail("Domain does not resolve for {$conceptId}.");
    $subjectId = (string) ($domain['subject_id'] ?? '');
    $config = $subjectConfig[$subjectId] ?? null;
    if (!is_array($config)) b695_fail("No board configuration for {$subjectId}.");
    $expectedSources = array_values(array_unique(array_map('strval', (array) ($conceptSources[$conceptId] ?? []))));
    if (count($expectedSources) < 2) b695_fail("Concept lacks a scope source plus subject source: {$conceptId}");
    foreach ($expectedSources as $sourceId) if (!isset($sourceById[$sourceId])) b695_fail("Unregistered source {$sourceId} for {$conceptId}.");
    $sectionId = (string) ($conceptSections[$conceptId] ?? '');
    if ($sectionId === '') b695_fail("No study section mapped for {$conceptId}.");
    $materialId = (string) $config['material'];
    $lessonPosition = $lessonIndex[$materialId] ?? null;
    if (!is_int($lessonPosition) || !isset($updatedLessonIds[$materialId])) b695_fail("Lesson update missing for concept {$conceptId}.");
    $lesson = $lessons[$lessonPosition];
    $sectionIds = array_map(static fn(array $section): string => (string) ($section['id'] ?? ''), (array) ($lesson['sections'] ?? []));
    if (!in_array($sectionId, $sectionIds, true)) b695_fail("Study section is missing for {$conceptId}: {$sectionId}");
    $section = null;
    foreach ((array) $lesson['sections'] as $candidate) if ((string) ($candidate['id'] ?? '') === $sectionId) { $section = $candidate; break; }
    if (!is_array($section) || mb_strlen((string) ($section['body'] ?? '')) < 200) b695_fail("Study section is too thin to teach {$conceptId}.");
    if (!is_array($rows) || count($rows) < 1 || count($rows) > 6) b695_fail("Invalid planned question count for {$conceptId}.");

    $difficultyCounts = array_count_values(array_map(static fn(array $row): string => (string) ($row['difficulty'] ?? ''), $rows));
    $requiredDifficultyCounts = str_starts_with($conceptId, 'arch-')
        ? ['medium'=>1]
        : (count($rows) === 6 ? ['easy'=>1,'medium'=>4,'hard'=>1] : ['easy'=>1,'medium'=>2,'hard'=>1]);
    foreach (['easy','medium','hard'] as $difficulty) if ((int) ($difficultyCounts[$difficulty] ?? 0) !== (int) ($requiredDifficultyCounts[$difficulty] ?? 0)) b695_fail("Difficulty plan mismatch for {$conceptId}.");

    $groupId = 'batch695-' . $config['board'] . '-' . $subjectId;
    if (isset($existingEligibilityIds[$groupId])) b695_fail("Eligibility group already exists: {$groupId}");
    if (!isset($batchEntries[$groupId])) $batchEntries[$groupId] = [
        'id'=>$groupId,
        'question_ids'=>[],
        'exam_ids'=>[$config['board']],
        'subject_ids'=>[$config['board']=>$subjectId],
        'question_mappings'=>[],
    ];

    foreach ($rows as $cardIndex => $card) {
        if (!is_array($card) || count((array) ($card['distractors'] ?? [])) !== 3) b695_fail("Incomplete four-choice item for {$conceptId} #" . ($cardIndex + 1));
        $stem = trim((string) ($card['question'] ?? ''));
        $answer = trim((string) ($card['answer'] ?? ''));
        $explanation = trim((string) ($card['explanation'] ?? ''));
        $competency = trim((string) ($card['tested_competency'] ?? ''));
        $scenario = trim((string) ($card['scenario'] ?? ''));
        if (mb_strlen($stem) < 50 || $answer === '' || mb_strlen($explanation) < 70 || $competency === '' || $scenario === '') b695_fail("Item missing stem, reasoning, competency or scenario for {$conceptId}.");
        $normalizedStem = b695_normalize($stem);
        if (isset($stemSet[$normalizedStem])) b695_fail("Duplicate normalized item stem in the Batch 695 question bank: {$stem}");
        $stemSet[$normalizedStem] = true;
        $normalizedChoices = array_map(static fn(mixed $choice): string => b695_normalize((string) $choice), array_merge([$answer], (array) $card['distractors']));
        if (in_array('', $normalizedChoices, true) || count(array_unique($normalizedChoices)) !== 4) b695_fail("Repeated or empty answer choices for {$conceptId}.");
        $allowedDifficulties = ['easy','medium','hard'];
        $difficulty = (string) ($card['difficulty'] ?? '');
        if (!in_array($difficulty, $allowedDifficulties, true)) b695_fail("Unknown difficulty for {$conceptId}: {$difficulty}");

        $id = $nextId++;
        $position = $globalIndex % 4;
        $choices = array_values((array) $card['distractors']);
        array_splice($choices, $position, 0, [$answer]);
        $answerPositions[$position]++;
        $globalIndex++;
        $template = $templateBySubject[$subjectId];
        $placement = array_merge((array) $placementBySubject[$subjectId], [
            'board_id'=>$config['board'],
            'subject_id'=>$subjectId,
            'domain_id'=>$domainId,
            'topic_id'=>(string) $concept['topic_id'],
            'concept_id'=>$conceptId,
        ]);
        if ($config['board'] === 'let') {
            $placement['let_level'] = $config['level'];
            $placement['let_component'] = 'SPECIALIZATION';
            $placement['specialization_id'] = $config['specialization'];
        }
        $question = $template;
        $question['id'] = $id;
        $question['board'] = $config['board'];
        $question['subject_id'] = $subjectId;
        $question['taxonomy'] = array_merge((array) ($template['taxonomy'] ?? []), [
            'board_id'=>$config['board'],
            'subject_id'=>$subjectId,
            'domain_id'=>$domainId,
            'topic_id'=>(string) $concept['topic_id'],
            'concept_id'=>$conceptId,
        ]);
        if ($config['board'] === 'architecture') {
            unset(
                $question['taxonomy']['subcompetency_id'],
                $question['taxonomy']['subcompetency_confidence'],
                $question['taxonomy']['subcompetency_evidence']
            );
        }
        // The selected eligibility template may carry labels and display-only
        // fields from an unrelated legacy item. Keep every new record aligned
        // with its own taxonomy and do not inherit unused table/figure exhibits.
        $question['domain'] = $domainId;
        $question['topic'] = (string) $concept['topic_id'];
        $question['concept'] = (string) ($concept['name'] ?? $conceptId);
        $question['content_blocks'] = [];
        $question['data_interpretation'] = false;
        $question['table_dependent'] = false;
        $question['figure_dependent'] = false;
        $question['site_plan_spatial'] = false;
        $question['status'] = 'active';
        $question['source'] = 'batch695-source-first';
        $question['batch_marker'] = 'batch695';
        $question['batch_extension'] = 'six-subject-depth-completion';
        $question['difficulty'] = $difficulty;
        $question['question'] = $stem;
        $question['choices'] = $choices;
        $question['answer'] = $answer;
        $question['explanation'] = $explanation;
        $question['tested_competency'] = $competency;
        $question['source_verification_status'] = 'VERIFIED_PRIMARY_OR_OFFICIAL_SOURCE';
        $question['academic_review_status'] = 'PENDING_INDEPENDENT_SUBJECT_EXPERT_REVIEW';
        $question['source_ids'] = $expectedSources;
        $question['study_material_id'] = $materialId;
        $question['study_section_id'] = $sectionId;
        $question['let_placement'] = array_merge((array) ($question['let_placement'] ?? []), $config['board'] === 'let' ? $placement : []);
        foreach (['timesUsed','timesCorrect','timesIncorrect','bookmarks','reports','helpfulExplanation','notHelpfulExplanation'] as $counter) $question[$counter] = 0;
        $question['createdAt'] = $timestamp;
        $question['updatedAt'] = $timestamp;
        $question['scenario_family'] = 'batch695-' . $conceptId . '-' . $scenario;
        $question['misconception_family'] = 'batch695-' . $conceptId . '-' . $scenario;
        $question['cognitive_intent'] = $competency;
        $question['case_interpretation'] = true;
        $question['professional_judgment'] = true;
        $question['multistep'] = $difficulty === 'hard';
        $question['numeric'] = false;

        $mapping = [
            'exam_ids'=>[$config['board']],
            'subject_ids'=>[$config['board']=>$subjectId],
            'placements'=>[$config['board']=>$placement],
        ];
        $batchEntries[$groupId]['question_ids'][] = (string) $id;
        $batchEntries[$groupId]['question_mappings'][(string) $id] = $mapping;
        $pendingMaps[] = ['id'=>(string) $id,'concept_id'=>$conceptId,'subject_id'=>$subjectId,'board_id'=>$config['board'],'placement'=>$placement,'group_id'=>$groupId];
        $pendingQuestions[] = $question;
        $links[] = ['id'=>'batch695-' . $id,'material_id'=>$materialId,'question_id'=>(string) $id,'source_ids'=>$expectedSources,'section_id'=>$sectionId];
        $questionCountBySubject[$subjectId]++;
        $questionCountByConcept[$conceptId] = ($questionCountByConcept[$conceptId] ?? 0) + 1;
    }
}

foreach ($targetQuestions as $subjectId => $expected) {
    if ((int) ($questionCountBySubject[$subjectId] ?? 0) !== $expected) b695_fail("Target addition count mismatch for {$subjectId}.");
}
if (array_sum($questionCountBySubject) !== 276 || count($pendingQuestions) !== 276) b695_fail('The six source-backed subject increments do not total 276.');
if (min($answerPositions) !== 69 || max($answerPositions) !== 69) b695_fail('New answer positions are not evenly distributed.');

$finalQuestions = array_merge($beforeQuestions, $pendingQuestions);
$eligibility = array_merge($eligibility, array_values($batchEntries));
foreach ($pendingMaps as $mapping) {
    $resolved = (new QuestionEligibilityIndex(
        $eligibility,
        $storage->all('blueprints'),
        QuestionReuseEligibilityResolver::productionDerivedRelationships(),
        $storage->all('question-reuse-overrides')
    ))->forExam($pendingQuestions[array_search($mapping['id'], array_map(static fn(array $q): string => (string) $q['id'], $pendingQuestions), true)], $mapping['board_id']);
    if (!is_array($resolved) || (string) ($resolved['subject_id'] ?? '') !== $mapping['subject_id']) b695_fail("Production eligibility resolver rejected question {$mapping['id']}.");
    $resolvedPlacement = is_array($resolved['placement'] ?? null) ? $resolved['placement'] : [];
    foreach (['board_id','subject_id','domain_id','topic_id','concept_id'] as $field) {
        if ((string) ($resolvedPlacement[$field] ?? '') !== (string) ($mapping['placement'][$field] ?? '')) b695_fail("Resolved placement mismatch for {$mapping['id']} at {$field}.");
    }
}

foreach ($pendingQuestions as $question) {
    $materialId = (string) ($question['study_material_id'] ?? '');
    $lessonPosition = $lessonIndex[$materialId] ?? null;
    if (!is_int($lessonPosition)) b695_fail('Question references a missing lesson.');
    $lesson = $lessons[$lessonPosition];
    $lesson['practice_question_ids'] = array_values(array_unique(array_merge(array_map('strval', (array) ($lesson['practice_question_ids'] ?? [])), [(string) $question['id']])));
    $lessons[$lessonPosition] = $lesson;
}

$sourcesFinalIndex = [];
foreach ($sources as $source) if (is_array($source)) $sourcesFinalIndex[(string) ($source['id'] ?? '')] = $source;
$existingCoverageIds = [];
foreach ($coverage as $row) if (is_array($row)) $existingCoverageIds[(string) ($row['id'] ?? '')] = true;
foreach ($conceptsData as $conceptId => $concept) {
    $topicId = (string) $concept['topic_id'];
    $topic = $topicById[$topicId];
    $domainId = (string) $topic['domain_id'];
    $domain = null;
    foreach ($domainRows as $candidate) if (is_array($candidate) && (string) ($candidate['id'] ?? '') === $domainId) { $domain = $candidate; break; }
    $subjectId = (string) ($domain['subject_id'] ?? '');
    $config = $subjectConfig[$subjectId];
    $placement = $placementBySubject[$subjectId];
    $coverageId = 'coverage-' . hash('sha256', $config['board'] . ':' . $conceptId);
    if (isset($existingCoverageIds[$coverageId])) b695_fail("Board concept coverage row already exists: {$conceptId}");
    $coverage[] = [
        'id'=>$coverageId,
        'board_id'=>$config['board'],
        'concept_id'=>$conceptId,
        'subject_id'=>$subjectId,
        'domain_id'=>$domainId,
        'topic_id'=>$topicId,
        'blueprint_node_id'=>(string) ($placement['blueprint_node'] ?? $placement['specialization_id'] ?? $subjectId),
        'max_depth'=>$config['board'] === 'architecture' ? 2 : 4,
        'reuse_policy'=>'REVIEW',
        'status'=>'VERIFIED',
        'provenance'=>[
            ['type'=>'BATCH695_SOURCE_EXPANSION','reference'=>'docs/reports/batch-695-source-expansion.md','note'=>'Official examination scope and subject source support this bounded concept placement.','verified_status'=>'VERIFIED','confidence'=>'MEDIUM'],
            ['type'=>'BATCH695_LESSON_AND_QUESTION_COHORT','reference'=>'batch-695','note'=>'Original lesson section and direct eligibility cohort are linked; independent academic review remains pending.','verified_status'=>'VERIFIED','confidence'=>'MEDIUM'],
        ],
        'evidence'=>'Current board scope, verified subject source, original BoardPrep lesson section, and direct production eligibility. Independent subject expert review remains pending.',
        'confidence'=>'MEDIUM',
    ];
    $existingCoverageIds[$coverageId] = true;
}

$readinessPaths = [
    'let'=>$root . '/storage/source-readiness-let.json',
    'architecture'=>$root . '/storage/source-readiness-architecture.json',
];
$readiness = [
    'let'=>b695_json($readinessPaths['let']),
    'architecture'=>b695_json($readinessPaths['architecture']),
];
$readinessIds = ['let'=>[],'architecture'=>[]];
foreach ($readiness as $board => $records) foreach ($records as $record) if (is_array($record)) $readinessIds[$board][(string) ($record['leaf_id'] ?? '')] = true;
foreach ($conceptsData as $conceptId => $concept) {
    $topic = $topicById[(string) $concept['topic_id']];
    $domainId = (string) $topic['domain_id'];
    $domain = null;
    foreach ($domainRows as $candidate) if (is_array($candidate) && (string) ($candidate['id'] ?? '') === $domainId) { $domain = $candidate; break; }
    $subjectId = (string) ($domain['subject_id'] ?? '');
    $config = $subjectConfig[$subjectId];
    $board = $config['board'] === 'let' ? 'let' : 'architecture';
    if (isset($readinessIds[$board][$conceptId])) b695_fail("Source-readiness row already exists: {$conceptId}");
    $sourcesForConcept = array_values(array_unique(array_map('strval', (array) $conceptSources[$conceptId])));
    $materialsForConcept = [(string) $config['material']];
    $questionCount = (int) ($questionCountByConcept[$conceptId] ?? 0);
    $sourcePrimaryCount = 0;
    foreach ($sourcesForConcept as $sourceId) {
        $source = $sourcesFinalIndex[$sourceId] ?? null;
        if (!is_array($source)) b695_fail("Readiness source missing: {$sourceId}");
        if (in_array((string) ($source['source_type'] ?? ''), ['PRIMARY_LAW','OFFICIAL_EXAM_SPEC'], true)) $sourcePrimaryCount++;
    }
    $current = 0;
    foreach ($finalQuestions as $question) if ((string) ($question['taxonomy']['concept_id'] ?? '') === $conceptId && strtolower((string) ($question['status'] ?? 'active')) !== 'archived') $current++;
    if ($current !== $questionCount) b695_fail("Readiness count mismatch for {$conceptId}: {$current} vs {$questionCount}");
    $readinessRow = [
        'leaf_id'=>$conceptId,
        'board_id'=>$config['board'],
        'official_area_id'=>$config['board'] === 'let' ? 'specialization' : 'arch-area-b',
        'leaf_name'=>(string) $concept['name'],
        'current_question_count'=>$current,
        'deficit_to_5'=>max(0,5-$current),
        'deficit_to_10'=>max(0,10-$current),
        'deficit_to_20'=>max(0,20-$current),
        'source_ids'=>$sourcesForConcept,
        'source_count'=>count($sourcesForConcept),
        'primary_source_count'=>$sourcePrimaryCount,
        'source_authority'=>'STRONG',
        'source_currentness'=>'CURRENT_WITH_LIMITS',
        'source_scope_fit'=>'DIRECT',
        'source_conflict_status'=>'NONE',
        'study_material_ids'=>$materialsForConcept,
        'study_material_count'=>1,
        'study_coverage'=>'COMPLETE',
        'study_source_traceability'=>'COMPLETE',
        'supported_depth_min'=>$config['board'] === 'architecture' ? 1 : 2,
        'supported_depth_max'=>$config['board'] === 'architecture' ? 2 : 4,
        'distinct_content_families'=>$config['board'] === 'architecture'
            ? ['ACCESSIBILITY_OR_CODE_APPLICATION','BUILDING_SYSTEMS_COORDINATION']
            : ['CLASSROOM_OR_ASSESSMENT_DECISION','EDUCATIONAL_CONCEPT_APPLICATION','PEDAGOGICAL_APPLICATION','RATIONALE_OR_EVIDENCE_ANALYSIS'],
        'distinct_content_family_count'=>$config['board'] === 'architecture' ? 2 : 4,
        'safe_headroom_min'=>0,
        'safe_headroom_preferred'=>0,
        'safe_headroom_max'=>0,
        'allowed_scope'=>[$conceptId],
        'excluded_scope'=>['Adjacent or unlisted concept leaves are excluded.','Edition-specific or unverified regulatory details are excluded.'],
        'limitations'=>['Authoring was bounded to the source-backed Batch 695 proposition set.','Independent subject-expert academic review remains pending.','Subject-matter and curriculum sources do not replace the current PRC examination scope.'],
        'readiness'=>'REVIEW',
        'readiness_reason'=>'The Batch 695 source-backed plan is applied and its bounded item count is consumed; further authoring needs review of the new evidence and content family.',
        'review_reason'=>'Independent academic review is pending; no automatic additional authoring is authorized for this newly expanded leaf.',
        'defer_reason'=>'',
        'max_new_questions'=>0,
        'planned_new_questions'=>0,
        'reviewed_at_batch'=>695,
        'closure_state'=>'OPEN',
    ];
    if ($config['board'] === 'let') {
        $readinessRow['scope_specialization_ids'] = [$config['specialization']];
        $readinessRow['scope_component_ids'] = ['SPECIALIZATION'];
        $readinessRow['scope_status'] = 'ACTIONABLE';
        $readinessRow['scope_crosswalk_status'] = 'VERIFIED_PARENT_SCOPE';
    }
    $readinessRow['evidence_hash'] = SourceReadinessRecord::evidenceHash($readinessRow);
    $readiness[$board][] = $readinessRow;
    $readinessIds[$board][$conceptId] = true;
}

foreach ($updatedLessonIds as $materialId => $_) {
    $lesson = $lessons[$lessonIndex[$materialId]];
    $ids = array_map('strval', (array) ($lesson['practice_question_ids'] ?? []));
    $counts = [];
    foreach ($questionData as $conceptId => $rows) {
        $topicId = (string) $conceptsData[$conceptId]['topic_id'];
        $domainId = (string) $topicById[$topicId]['domain_id'];
        $subjectId = '';
        foreach ($domainRows as $domain) if ((string) ($domain['id'] ?? '') === $domainId) { $subjectId = (string) ($domain['subject_id'] ?? ''); break; }
        if (($subjectConfig[$subjectId]['material'] ?? '') === $materialId) $counts[$conceptId] = count($rows);
    }
    $expectedForLesson = array_sum($counts);
    $addedForLesson = 0;
    foreach ($pendingQuestions as $question) if ((string) ($question['study_material_id'] ?? '') === $materialId) $addedForLesson++;
    if ($expectedForLesson !== $addedForLesson) b695_fail("Lesson practice count does not match the new linked question count: {$materialId}");
    if (count(array_intersect(
        array_map('strval', (array) $lesson['practice_question_ids']),
        array_map(static fn(array $q): string => (string) $q['id'], array_values(array_filter(
            $pendingQuestions,
            static fn(array $q): bool => (string) $q['study_material_id'] === $materialId
        )))
    )) !== $expectedForLesson) {
        b695_fail("Lesson is missing practice_question_ids for {$materialId}");
    }
}

$writeCollections = [
    $root . '/storage/sources.json'=>$sources,
    $root . '/storage/taxonomy/topics.json'=>$topics,
    $root . '/storage/taxonomy/concepts.json'=>$concepts,
    $root . '/storage/study-lessons.json'=>$lessons,
    $root . '/storage/questions.json'=>$finalQuestions,
    $root . '/storage/question-eligibility.json'=>$eligibility,
    $root . '/storage/study-question-links.json'=>$links,
    $root . '/storage/board-concept-coverage.json'=>$coverage,
    $readinessPaths['let']=>$readiness['let'],
    $readinessPaths['architecture']=>$readiness['architecture'],
];

if ($mode === '--apply') {
    $free = disk_free_space($root);
    if (!is_float($free) && !is_int($free)) b695_fail('Could not read available storage before apply.');
    if ($free < 1_610_612_736) b695_fail('Less than 1.5 GiB remains free; refusing Batch 695 writes.');
    $prepared = [];
    foreach ($writeCollections as $path => $data) {
        $temporary = $path . '.batch695-tmp';
        if (is_file($temporary)) b695_fail("Pre-existing temporary file requires inspection: {$temporary}");
        $payload = b695_json_payload($data);
        $permissions = fileperms($path);
        $modeBits = $permissions === false ? 0644 : ($permissions & 0777);
        $written = file_put_contents($temporary, $payload, LOCK_EX);
        if ($written !== strlen($payload)) {
            foreach ($prepared as $temp) @unlink($temp);
            @unlink($temporary);
            b695_fail("Short temporary write for {$path}.");
        }
        @chmod($temporary, $modeBits);
        $prepared[$path] = $temporary;
    }
    foreach ($prepared as $path => $temporary) {
        if (!rename($temporary, $path)) {
            foreach ($prepared as $remaining) if (is_file($remaining)) @unlink($remaining);
            b695_fail("Rename failed during the prepared atomic writes for {$path}; inspect the batch state before retrying.");
        }
    }
    $writtenQuestions = b695_json($root . '/storage/questions.json');
    $writtenById = [];
    foreach ($writtenQuestions as $question) $writtenById[(string) ($question['id'] ?? '')] = $question;
    foreach ((array) ($baseline['hashes'] ?? []) as $id => $hash) {
        if (!isset($writtenById[(string) $id])) b695_fail("A pre-existing question was lost after write: {$id}");
        $actualHash = hash('sha256', json_encode($writtenById[(string) $id], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR));
        if (!hash_equals((string) $hash, $actualHash)) b695_fail("A pre-existing question changed after write: {$id}");
    }
    printf("BATCH695_APPLY=PASS\nQUESTIONS_ADDED=%d\nIDS=%d-%d\nSOURCES_ADDED=%d\nTOPICS_ADDED=%d\nCONCEPTS_ADDED=%d\nLESSONS_EXPANDED=%d\nELIGIBILITY_ENTRIES_ADDED=%d\nLEARNER_RECORDS_UNCHANGED=%d\nANSWER_POSITIONS=%d/%d/%d/%d\n", count($pendingQuestions), (int) $pendingQuestions[0]['id'], (int) $pendingQuestions[array_key_last($pendingQuestions)]['id'], count((array) $evidence['sources']), count($topicsData), count($conceptsData), count($updatedLessonIds), count($batchEntries), count($baseline['hashes']), $answerPositions[0], $answerPositions[1], $answerPositions[2], $answerPositions[3]);
} else {
    printf("BATCH695_DRY_RUN=PASS\nQUESTIONS=%d\nSUBJECT_ADDITIONS=%s\nCONCEPTS=%d\nTOPICS=%d\nSOURCES=%d\nLESSONS=%d\nQUESTION_IDS=%d-%d\nANSWER_POSITIONS=%d/%d/%d/%d\n", count($pendingQuestions), json_encode($questionCountBySubject, JSON_UNESCAPED_SLASHES), count($conceptsData), count($topicsData), count((array) $evidence['sources']), count($updatedLessonIds), (int) $pendingQuestions[0]['id'], (int) $pendingQuestions[array_key_last($pendingQuestions)]['id'], $answerPositions[0], $answerPositions[1], $answerPositions[2], $answerPositions[3]);
}
