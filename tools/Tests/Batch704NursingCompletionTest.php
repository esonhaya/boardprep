<?php

declare(strict_types=1);

putenv('APP_ENV=production');
putenv('APP_DEBUG=false');
putenv('APP_TIMEZONE=UTC');
putenv('DB_DRIVER=json');

require_once dirname(__DIR__) . '/Doctor/Project/BoardPrep/Simulation/HttpSimulator.php';
require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

use App\Core\App;
use App\Services\Question\QuestionEligibilityService;
use App\Services\Study\SourceRegistryService;
use App\Services\Study\StudyLibraryService;
use App\Storage\JsonStorage;
use Tools\Doctor\Project\BoardPrep\Simulation\HttpSimulator;

$root = dirname(__DIR__, 2);
$read = static function (string $path) use ($root): array {
    $value = json_decode((string) file_get_contents($root . '/' . $path), true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($value)) throw new RuntimeException('Expected JSON array/object: ' . $path);
    return $value;
};
$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};

$http = new HttpSimulator($root . '/public/index.php');
$storagePath = $http->storagePath();
putenv('APP_STORAGE_PATH=' . $storagePath);
putenv('APP_SESSION_PATH=' . $http->sessionPath());
App::boot();

$lessons = $read('storage/study-lessons.json');
$materials = $read('storage/study-materials.json');
$links = $read('storage/study-question-links.json');
$sources = $read('storage/sources.json');
$questions = $read('storage/questions.json');
$eligibilityRows = $read('storage/question-eligibility.json');
$concepts = $read('storage/taxonomy/concepts.json');
$coverage = $read('storage/board-concept-coverage.json');
$baseline = $read('docs/reports/batch-704-nursing-coverage-before.json');
$manifest = $read('public/study-resources/manifest.json');

$lessonById = [];
$lessonByMaterial = [];
foreach ($lessons as $lesson) {
    if (!is_array($lesson)) continue;
    $lessonById[(string) ($lesson['id'] ?? '')] = $lesson;
    $lessonByMaterial[(string) ($lesson['material_id'] ?? '')] = $lesson;
}
$materialById = [];
foreach ($materials as $material) if (is_array($material)) $materialById[(string) ($material['id'] ?? '')] = $material;
$sourceById = [];
foreach ($sources as $source) if (is_array($source)) $sourceById[(string) ($source['id'] ?? '')] = $source;
$questionById = [];
foreach ($questions as $question) if (is_array($question)) $questionById[(string) ($question['id'] ?? '')] = $question;

$sourceValidation = SourceRegistryService::validateSnapshot($sources);
$assert(($sourceValidation['valid'] ?? false) === true, 'Source registry failed: ' . json_encode($sourceValidation['issues'] ?? []));
$studyValidation = StudyLibraryService::validateSnapshot([
    'study-materials' => $materials,
    'study-question-links' => $links,
    'sources' => $sources,
    'questions' => $questions,
    'question-eligibility' => $eligibilityRows,
    'study-lessons' => $lessons,
]);
$errors = array_values(array_filter((array) ($studyValidation['issues'] ?? []), static fn(array $issue): bool => ($issue['severity'] ?? '') === 'ERROR'));
$assert(($studyValidation['valid'] ?? false) === true && $errors === [], 'Study library integrity failed: ' . json_encode(array_slice($errors, 0, 10)));
$assert(hash_file('sha256', $root . '/storage/questions.json') === '73cdc775cd7240b57898868d67902d833db1171f947c8dcc89081ddaf46fb4be', 'Canonical question content or answer keys changed.');

$requiredLessons = [
    'nursing-disaster-preparedness-and-continuity',
    'nursing-digestive-endocrine-assessment',
    'nursing-maternal-family-assessment',
    'nursing-newborn-safety-assessment',
    'nursing-oxygenation-fluid-immunity-assessment',
    'nursing-therapeutic-communication-and-crisis',
];
$newSections = [
    'b704-disaster-risk-cycle', 'b704-disaster-community-coordination',
    'b704-gi-digestion-absorption', 'b704-gi-fluid-pattern-assessment',
    'b704-endocrine-signaling', 'b704-endocrine-context-and-trends',
    'b704-labor-coping-and-choice', 'b704-labor-progress-context',
    'b704-newborn-thermal-protection', 'b704-newborn-danger-signs-referral',
    'b704-cellular-specimen-evidence', 'b704-cellular-report-and-limits',
];
$expectedMappings = [
    '879' => ['nursing-disaster-preparedness-and-continuity', 'b704-disaster-community-coordination'],
    '894' => ['nursing-maternal-family-assessment', 'b704-labor-coping-and-choice'],
    '1523' => ['nursing-newborn-safety-assessment', 'b704-newborn-thermal-protection'],
    '3855' => ['nursing-newborn-safety-assessment', 'b704-newborn-thermal-protection'],
    '4037' => ['nursing-oxygenation-fluid-immunity-assessment', 'b704-cellular-specimen-evidence'],
    '4057' => ['nursing-digestive-endocrine-assessment', 'b704-gi-fluid-pattern-assessment'],
];
$assert(count($expectedMappings) === 6, 'Expected six evidence-supported B704 exact candidates.');
$conceptIds = array_fill_keys(array_map('strval', array_column($concepts, 'id')), true);
$sectionOwners = [];
$newSectionCount = 0;
foreach ($requiredLessons as $lessonId) {
    $lesson = $lessonById[$lessonId] ?? null;
    $assert(is_array($lesson) && ($lesson['board_id'] ?? '') === 'nursing' && ($lesson['status'] ?? '') === 'published', 'Missing published Nursing lesson: ' . $lessonId);
    $assert(isset($materialById[(string) $lesson['material_id']]), 'Missing lesson material: ' . $lesson['material_id']);
    $assert(in_array('nursing', array_map('strtolower', array_map('strval', (array) $materialById[(string) $lesson['material_id']]['exams'])), true), 'Lesson material is not Nursing scoped: ' . $lessonId);
    $assert(($lesson['source_review']['status'] ?? '') === 'SOURCE_TEXT_CHECKED_WITH_LIMITATIONS; INDEPENDENT_NURSING_REVIEW_PENDING', 'Lesson was promoted beyond independent review: ' . $lessonId);
    foreach (array_merge((array) ($lesson['official_scope_source_ids'] ?? []), (array) ($lesson['instructional_source_ids'] ?? [])) as $sourceId) {
        $assert(isset($sourceById[(string) $sourceId]), 'Lesson references unknown source: ' . $lessonId . '/' . $sourceId);
    }
    foreach ((array) ($lesson['sections'] ?? []) as $section) {
        $sectionId = (string) ($section['id'] ?? '');
        $sectionOwners[$sectionId] = $lessonId;
        if (!in_array($sectionId, $newSections, true)) continue;
        $newSectionCount++;
        $body = trim((string) ($section['body'] ?? ''));
        $assert(strlen($body) >= 350, 'New section is not substantively written: ' . $sectionId);
        $related = array_values(array_map('strval', (array) ($section['related_concept_ids'] ?? [])));
        $assert(count($related) === 1 && isset($conceptIds[$related[0]]), 'New section has missing or invalid concept relationship: ' . $sectionId);
        $sectionSources = array_values(array_unique(array_map('strval', (array) ($section['source_ids'] ?? []))));
        $assert($sectionSources !== [], 'New section has no source: ' . $sectionId);
        foreach ($sectionSources as $sourceId) {
            $assert(isset($sourceById[$sourceId]), 'New section has unregistered source: ' . $sectionId . '/' . $sourceId);
            $assert(in_array($sourceId, array_map('strval', (array) $lesson['instructional_source_ids']), true), 'Section source is absent from lesson source list: ' . $sectionId . '/' . $sourceId);
        }
    }
}
$assert($newSectionCount === 12, 'Expected 12 substantive B704 section additions; got ' . $newSectionCount);

$npCoverage = [];
foreach (['i', 'ii', 'iii', 'iv', 'v'] as $level) {
    $rows = array_values(array_filter($concepts, static fn(array $concept): bool => str_starts_with((string) ($concept['id'] ?? ''), 'nle-np-' . $level . '-')));
    $covered = [];
    foreach ($lessons as $lesson) {
        if (($lesson['board_id'] ?? '') !== 'nursing' || ($lesson['status'] ?? '') !== 'published') continue;
        foreach ((array) ($lesson['sections'] ?? []) as $section) {
            if (strlen(trim((string) ($section['body'] ?? ''))) < 350) continue;
            foreach ((array) ($section['related_concept_ids'] ?? []) as $conceptId) {
                if (str_starts_with((string) $conceptId, 'nle-np-' . $level . '-')) $covered[(string) $conceptId] = true;
            }
        }
    }
    $assert(count($rows) === 6, 'NP level concept denominator changed: ' . $level);
    $npCoverage[strtoupper($level)] = count($covered);
}
$assert($npCoverage === ['I' => 6, 'II' => 6, 'III' => 6, 'IV' => 5, 'V' => 6], 'Unexpected NP structural result: ' . json_encode($npCoverage));
$sharedIds = array_column(array_filter($coverage, static fn(array $row): bool => ($row['board_id'] ?? '') === 'nursing' && str_starts_with((string) ($row['concept_id'] ?? ''), 'science-')), 'concept_id');
$assert(count($sharedIds) === 10, 'Shared-science placement denominator changed.');
foreach ($lessons as $lesson) {
    if (($lesson['board_id'] ?? '') !== 'nursing') continue;
    foreach ((array) ($lesson['sections'] ?? []) as $section) {
        foreach ((array) ($section['related_concept_ids'] ?? []) as $conceptId) {
            $assert(!str_starts_with((string) $conceptId, 'science-'), 'Shared science was silently added as a Nursing section: ' . $conceptId);
        }
    }
}

$linkByPair = [];
foreach ($links as $link) {
    if (!is_array($link)) continue;
    $linkByPair[(string) ($link['material_id'] ?? '') . "\0" . (string) ($link['question_id'] ?? '')] = $link;
}
$eligibility = QuestionEligibilityService::index();
$refs = StudyLibraryService::forQuestions(array_keys($expectedMappings), 'nursing', 'nursing-foundations');
foreach ($expectedMappings as $questionId => [$lessonId, $sectionId]) {
    $question = $questionById[$questionId] ?? null;
    $assert(is_array($question) && ($question['status'] ?? '') === 'active' && ($question['taxonomy']['board_id'] ?? '') === 'nursing', 'Question identity or board invalid: ' . $questionId);
    $eligible = $eligibility->forExam($question, 'nursing');
    $assert(is_array($eligible) && ($eligible['exam_id'] ?? '') === 'nursing', 'Question not eligible for Nursing: ' . $questionId);
    $lesson = $lessonById[$lessonId];
    $link = $linkByPair[(string) $lesson['material_id'] . "\0" . $questionId] ?? null;
    $assert(is_array($link) && ($link['lesson_id'] ?? '') === $lessonId && ($link['section_id'] ?? '') === $sectionId, 'Exact new section relationship missing: ' . $questionId);
    $assert(($link['semantic_review_status'] ?? '') === 'SECTION_AND_SOURCE_CHECKED_PENDING_INDEPENDENT_ACADEMIC_REVIEW', 'New mapping not pending independent review: ' . $questionId);
    $assert(in_array((string) $questionId, array_map('strval', (array) ($lesson['practice_question_ids'] ?? [])), true), 'New mapping not represented in lesson practice list: ' . $questionId);
    $anchor = StudyLibraryService::lessonSectionAnchorId($lessonId, $sectionId);
    $found = false;
    foreach ((array) ($refs[$questionId] ?? []) as $ref) if (($ref['lesson_id'] ?? '') === $lessonId && ($ref['section_id'] ?? '') === $sectionId && str_contains((string) ($ref['url'] ?? ''), $anchor)) $found = true;
    $assert($found, 'Answer review reference/anchor missing: ' . $questionId);
}
foreach (['4004', '4005', '4006'] as $questionId) {
    $nursingExact = array_filter($links, static fn(array $link): bool => (string) ($link['question_id'] ?? '') === $questionId && str_starts_with((string) ($link['lesson_id'] ?? ''), 'nursing-') && !empty($link['section_id']));
    $assert($nursingExact === [], 'Demoted section-mismatch mapping remains exact: ' . $questionId);
    $sourceOnly = array_filter($links, static fn(array $link): bool => (string) ($link['question_id'] ?? '') === $questionId && !empty($link['source_ids']) && empty($link['section_id']));
    $assert($sourceOnly !== [], 'Demoted mapping lost its retained source-only relationship: ' . $questionId);
}
foreach (['31', '32', '33', '34', '35', '36', '37', '40', '107', '111'] as $questionId) {
    foreach ($links as $link) {
        if ((string) ($link['question_id'] ?? '') !== $questionId) continue;
        $assert(!str_starts_with((string) ($link['lesson_id'] ?? ''), 'nursing-'), 'Cross-board shared-science exact link leaked into Nursing: ' . $questionId);
    }
}

$manifestByLesson = [];
foreach ((array) ($manifest['files'] ?? []) as $entry) $manifestByLesson[(string) ($entry['lesson_id'] ?? '')] = $entry;
$assert(count($manifestByLesson) === 59, 'Expected 59 manifest entries after three new Nursing exports.');
$regeneratedExisting = array_fill_keys([
    'nursing-maternal-family-assessment',
    'nursing-oxygenation-fluid-immunity-assessment',
    'nursing-therapeutic-communication-and-crisis',
], true);
foreach ((array) ($baseline['pdf_assets_before'] ?? []) as $old) {
    $id = (string) ($old['lesson_id'] ?? '');
    $current = $manifestByLesson[$id] ?? null;
    $assert(is_array($current) && ($current['path'] ?? '') === ($old['path'] ?? ''), 'Existing PDF identity/path changed: ' . $id);
    if (!isset($regeneratedExisting[$id])) {
        $assert(($current['sha256'] ?? '') === ($old['manifest_sha256'] ?? '')
            && ($current['lesson_sha256'] ?? '') === ($old['lesson_sha256'] ?? ''), 'Unchanged existing PDF evidence was rewritten: ' . $id);
    }
    $path = $root . '/' . ltrim((string) $current['path'], '/');
    $assert(is_file($path) && hash_file('sha256', $path) === ($current['sha256'] ?? ''), 'PDF asset hash mismatch: ' . $id);
}
foreach ($manifestByLesson as $id => $entry) {
    $path = $root . '/' . ltrim((string) ($entry['path'] ?? ''), '/');
    $assert(is_file($path) && hash_file('sha256', $path) === ($entry['sha256'] ?? ''), 'Manifest SHA-256 does not match asset bytes: ' . $id);
}
$claimLedger = $read('docs/reports/batch-704-nursing-source-claim-ledger.json');
$ledgerPairs = [];
foreach ((array) ($claimLedger['records'] ?? []) as $record) $ledgerPairs[(string) ($record['SECTION_ID'] ?? '') . "\0" . (string) ($record['SOURCE_ID'] ?? '')] = true;
foreach ($newSections as $sectionId) {
    $section = null;
    foreach ($lessons as $candidateLesson) foreach ((array) ($candidateLesson['sections'] ?? []) as $candidateSection) if (($candidateSection['id'] ?? '') === $sectionId) $section = $candidateSection;
    $assert(is_array($section), 'Source-ledger section is missing: ' . $sectionId);
    foreach ((array) ($section['source_ids'] ?? []) as $sourceId) $assert(isset($ledgerPairs[$sectionId . "\0" . $sourceId]), 'Source-to-claim ledger omits section/source pair: ' . $sectionId . '/' . $sourceId);
}
foreach ((array) ($baseline['mutable_state_before_testing'] ?? []) as $path => $state) {
    $file = $root . '/' . $path;
    if (($state['status'] ?? '') === 'PRESENT') $assert(is_file($file) && hash_file('sha256', $file) === ($state['sha256'] ?? ''), 'Mutable learner/canonical state changed during isolated tests: ' . $path);
    elseif (($state['status'] ?? '') === 'ABSENT_IN_B704_BRANCH_CHECKOUT') $assert(!is_file($file), 'A test unexpectedly created branch-local mutable state: ' . $path);
}

$cookies = ['PHPSESSID' => 'batch704-nursing-' . bin2hex(random_bytes(8))];
$requireSuccess = static function (array $response, string $label): void {
    if (!$response['success'] || ($response['status'] ?? 0) !== 200) {
        $message = substr(preg_replace('/\s+/', ' ', strip_tags((string) ($response['output'] ?? '') . (string) ($response['stderr'] ?? ''))) ?? '', 0, 240);
        throw new RuntimeException($label . ' failed, HTTP ' . ($response['status'] ?? 0) . ': ' . $message);
    }
};
$library = $http->request('GET', '/study-library', ['exam' => 'nursing', 'subject' => 'nursing-foundations'], [], [], $cookies);
$requireSuccess($library, 'Nursing subject/material listing');
foreach ($requiredLessons as $lessonId) {
    $materialId = (string) $lessonById[$lessonId]['material_id'];
    $assert(str_contains($library['output'], (string) $materialById[$materialId]['title']), 'Lesson material missing from the Nursing subject view: ' . $materialId);
}
foreach (['nursing-disaster-preparedness-and-continuity', 'nursing-digestive-endocrine-assessment', 'nursing-maternal-family-assessment', 'nursing-newborn-safety-assessment', 'nursing-oxygenation-fluid-immunity-assessment'] as $lessonId) {
    $lesson = $lessonById[$lessonId];
    $detail = $http->request('GET', '/study-library', ['exam' => 'nursing', 'subject' => 'nursing-foundations', 'material' => $lesson['material_id']], [], [], $cookies);
    $requireSuccess($detail, 'Nursing lesson route ' . $lessonId);
    $assert(str_contains($detail['output'], (string) $lesson['title']), 'Lesson title missing: ' . $lessonId);
    foreach ((array) ($lesson['sections'] ?? []) as $section) {
        $anchor = StudyLibraryService::lessonSectionAnchorId($lessonId, (string) $section['id']);
        $assert(str_contains($detail['output'], 'id="' . $anchor . '"'), 'Lesson section anchor missing: ' . $anchor);
    }
}
$empty = $http->request('GET', '/study-library/practice', ['material' => 'nle-np-i-prevention', 'section' => 'b704-disaster-risk-cycle'], [], [], $cookies);
$assert(($empty['status'] ?? 0) === 303 && str_contains((string) ($empty['location'] ?? ''), 'nle-np-i-prevention'), 'Empty exact pool did not return safely to its lesson.');
$stale = $http->request('GET', '/study-library/practice', ['material' => 'nle-np-i-prevention', 'section' => 'stale-section-anchor'], [], [], $cookies);
$assert(($stale['status'] ?? 0) === 303 && str_contains((string) ($stale['location'] ?? ''), '#boardprep-lesson-nursing-disaster-preparedness-and-continuity'), 'Stale section navigation did not recover to the lesson root.');
$foreign = $http->request('GET', '/study-library', ['exam' => 'let', 'subject' => 'english', 'material' => 'nle-np-i-prevention'], [], [], $cookies);
$requireSuccess($foreign, 'Cross-board isolation route');
$assert(!str_contains($foreign['output'], (string) $lessonById['nursing-disaster-preparedness-and-continuity']['title']), 'Nursing lesson leaked into LET English.');

$complete = static function (string $materialId, string $sectionId, bool $correct, bool $repeat) use ($http, $questionById, $cookies, $requireSuccess, $assert): void {
    $session = ['PHPSESSID' => 'batch704-answer-' . bin2hex(random_bytes(6))];
    $start = $http->request('GET', '/study-library/practice', ['material' => $materialId, 'section' => $sectionId], [], [], $session);
    $requireSuccess($start, 'Exact-section practice ' . $sectionId);
    if (preg_match('/name="question_id"\s+value="([^"]+)"/', $start['output'], $qid) !== 1
        || preg_match('/name="csrf_token"\s+value="([a-f0-9]{64})"/', $start['output'], $csrf) !== 1) throw new RuntimeException('Practice page omitted question/CSRF: ' . $sectionId);
    $questionId = $qid[1];
    $question = $questionById[$questionId] ?? null;
    $assert(is_array($question), 'Practice selected missing canonical question: ' . $questionId);
    $right = null; $wrong = null;
    foreach (array_values((array) ($question['choices'] ?? [])) as $i => $choice) {
        $letter = chr(65 + $i);
        if (strcasecmp(trim((string) $choice), trim((string) ($question['answer'] ?? ''))) === 0) $right = $letter;
        elseif ($wrong === null) $wrong = $letter;
    }
    $assert($right !== null && $wrong !== null, 'Question choices do not represent answer and distractor: ' . $questionId);
    $answer = $correct ? $right : $wrong;
    $submitted = $http->request('POST', '/quiz', [], ['action' => 'submit', 'question_id' => $questionId, 'answer' => $answer, 'csrf_token' => $csrf[1]], [], $session);
    $requireSuccess($submitted, 'Answer submission ' . $questionId);
    $assert(str_contains($submitted['output'], $correct ? 'Correct!' : 'Incorrect'), 'Correct/incorrect feedback mismatch: ' . $questionId);
    $finish = $http->request('POST', '/quiz', [], ['action' => 'finish', 'csrf_token' => $csrf[1]], [], $session);
    $assert(($finish['status'] ?? 0) === 303, 'Practice completion did not redirect: ' . $questionId);
    $review = $http->request('GET', '/quiz', ['action' => 'result'], [], [], $session);
    $requireSuccess($review, 'Answer review ' . $questionId);
    $assert(str_contains($review['output'], 'Open supporting lesson section:'), 'Answer review omitted supporting section link: ' . $questionId);
    if ($repeat) {
        $again = $http->request('GET', '/study-library/practice', ['material' => $materialId, 'section' => $sectionId], [], [], $session);
        $requireSuccess($again, 'Repeated section practice ' . $sectionId);
        $assert(preg_match('/name="question_id"\s+value="([^"]+)"/', $again['output'], $repeatId) === 1, 'Repeated practice did not restart: ' . $sectionId);
    }
};
$complete('nle-np-ii-foundation', 'b704-labor-coping-and-choice', true, true);
$complete('nle-np-i-prevention', 'b704-disaster-community-coordination', true, false);
$complete('nle-np-ii-newborn', 'b704-newborn-thermal-protection', true, false);
$complete('nle-np-iii-oxygenation', 'b704-cellular-specimen-evidence', false, false);
$complete('nle-np-iv-endocrine', 'b704-gi-fluid-pattern-assessment', true, false);

fwrite(STDOUT, "[PASS] B704 lessons, 12 substantive sections, registered provenance, Nursing eligibility, 29/30 NP structural coverage, and shared-science isolation validated.\n");
fwrite(STDOUT, "[PASS] Six exact section mappings remain independently review-pending; three weak Batch 702 mappings retain source-only records.\n");
fwrite(STDOUT, "[PASS] PDF manifest contains 59 intact entries and all 56 prior asset identities retain their paths and valid hashes.\n");
fwrite(STDOUT, "[PASS] Isolated HTTP flows cover lesson navigation, empty/stale pool recovery, cross-board rejection, correct/incorrect review, repeat practice, and linked sections.\n");
fwrite(STDOUT, "[PASS] Canonical questions were unchanged; learner HTTP mutations stayed in disposable HttpSimulator storage.\n");
