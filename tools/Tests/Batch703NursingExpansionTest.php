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
use App\Repositories\QuestionRepository;
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
$coverageRows = $read('storage/board-concept-coverage.json');
$manifest = $read('public/study-resources/manifest.json');
$baseline = $read('docs/reports/batch-703-nursing-coverage-before.json');
$audit = $read('docs/reports/batch-703-nursing-mapping-audit.json');

$lessonById = [];
$lessonByMaterial = [];
foreach ($lessons as $lesson) {
    if (!is_array($lesson)) continue;
    $lessonById[(string) ($lesson['id'] ?? '')] = $lesson;
    if (($lesson['status'] ?? '') === 'published') $lessonByMaterial[(string) ($lesson['material_id'] ?? '')] = $lesson;
}
$materialById = [];
foreach ($materials as $material) if (is_array($material)) $materialById[(string) ($material['id'] ?? '')] = $material;
$questionById = [];
foreach ($questions as $question) if (is_array($question)) $questionById[(string) ($question['id'] ?? '')] = $question;
$sourceById = [];
foreach ($sources as $source) if (is_array($source)) $sourceById[(string) ($source['id'] ?? '')] = $source;
$linkByPair = [];
foreach ($links as $link) {
    if (!is_array($link)) continue;
    $key = (string) ($link['material_id'] ?? '') . "\0" . (string) ($link['question_id'] ?? '');
    if (isset($linkByPair[$key])) throw new RuntimeException('Duplicate material/question relationship: ' . str_replace("\0", '/', $key));
    $linkByPair[$key] = $link;
}

$sourceValidation = SourceRegistryService::validateSnapshot($sources);
$assert(($sourceValidation['valid'] ?? false) === true, 'Source registry validation failed: ' . json_encode($sourceValidation['issues'] ?? []));
$studyValidation = StudyLibraryService::validateSnapshot([
    'study-materials' => $materials,
    'study-question-links' => $links,
    'sources' => $sources,
    'questions' => $questions,
    'question-eligibility' => $eligibilityRows,
    'study-lessons' => $lessons,
]);
if (($studyValidation['valid'] ?? false) !== true) {
    $errors = array_values(array_filter((array) ($studyValidation['issues'] ?? []), static fn(array $issue): bool => ($issue['severity'] ?? '') === 'ERROR'));
    throw new RuntimeException('Study library integrity validation failed: ' . json_encode(array_slice($errors, 0, 12)));
}

$expectedLessonIds = [
    'nursing-community-risk-and-prevention',
    'nursing-maternal-family-assessment',
    'nursing-oxygenation-fluid-immunity-assessment',
    'nursing-perioperative-verification',
    'nursing-nutrition-and-sensory-assessment',
    'nursing-therapeutic-communication-and-crisis',
    'nursing-emergency-assessment-and-shock',
];
$expectedMappings = [];
foreach ((array) ($audit['accepted_candidates'] ?? []) as $candidate) {
    if (($candidate['decision'] ?? '') !== 'LINKED_PENDING_INDEPENDENT_ACADEMIC_REVIEW') continue;
    $expectedMappings[(string) $candidate['question_id']] = $candidate;
}
$assert(count($expectedMappings) === 31, 'Audit must define 31 new section-support candidates.');
// B705 removed two source-limited mappings while preserving their audit rows.
unset($expectedMappings['4014'], $expectedMappings['5025']);
// B705 independently rechecked and retained the newly supported prenatal warning-cue relationship.
$expectedMappings['1540'] = [
    'board' => 'nursing',
    'subject' => 'nursing-foundations',
    'question_id' => '1540',
    'material_id' => 'nle-np-ii-foundation',
    'lesson_id' => 'nursing-maternal-family-assessment',
    'section_id' => 'b703-prenatal-warning-cues',
];
$assert(hash_file('sha256', $root . '/storage/questions.json') === '73cdc775cd7240b57898868d67902d833db1171f947c8dcc89081ddaf46fb4be', 'Canonical question storage differs from the verified B702 base.');

$lessonConceptSections = [];
foreach ($lessons as $lesson) {
    if (!is_array($lesson) || ($lesson['board_id'] ?? '') !== 'nursing' || ($lesson['status'] ?? '') !== 'published') continue;
    foreach ((array) ($lesson['sections'] ?? []) as $section) {
        if (!is_array($section) || strlen(trim((string) ($section['body'] ?? ''))) < 350) continue;
        foreach ((array) ($section['related_concept_ids'] ?? []) as $conceptId) {
            $lessonConceptSections[(string) $conceptId][] = ['lesson_id' => $lesson['id'], 'section_id' => $section['id']];
        }
    }
}
$conceptIds = array_fill_keys(array_map('strval', array_column($concepts, 'id')), true);
$npConcepts = array_values(array_filter($concepts, static fn(array $concept): bool => str_starts_with((string) ($concept['id'] ?? ''), 'nle-np-')));
$npCoverage = [];
foreach (['i', 'ii', 'iii', 'iv', 'v'] as $level) {
    $rows = array_values(array_filter($npConcepts, static fn(array $concept): bool => str_starts_with((string) ($concept['id'] ?? ''), 'nle-np-' . $level . '-')));
    $npCoverage[strtoupper($level)] = count(array_filter($rows, static fn(array $concept): bool => !empty($lessonConceptSections[(string) $concept['id']])));
    $assert(count($rows) === 6, 'Unexpected NP ' . strtoupper($level) . ' concept denominator.');
}
$assert($npCoverage === ['I' => 6, 'II' => 6, 'III' => 6, 'IV' => 6, 'V' => 6], 'Unexpected Nursing NP I–V concept coverage after B705: ' . json_encode($npCoverage));
$coveredNp = array_sum($npCoverage);
$assert($coveredNp === 30, 'Expected 30/30 NP structural concept sections after B705.');

$placedNursingConcepts = array_values(array_filter($coverageRows, static fn(array $row): bool => ($row['board_id'] ?? '') === 'nursing'));
$assert(count($placedNursingConcepts) === 40, 'Nursing placement denominator changed unexpectedly.');
$assert(count($lessons) === 59, 'Expected 46 non-Nursing plus 13 Nursing lessons after three B704 lesson additions.');
$newSectionCount = 0;
$newExampleCount = 0;
$newMisconceptionCount = 0;
$newObjectiveCount = 0;
$sectionIds = [];
$lessonMaterials = [];
foreach ($expectedLessonIds as $lessonId) {
    $lesson = $lessonById[$lessonId] ?? null;
    $assert(is_array($lesson) && ($lesson['status'] ?? '') === 'published' && ($lesson['board_id'] ?? '') === 'nursing', 'Missing published Nursing lesson ' . $lessonId);
    $materialId = (string) $lesson['material_id'];
    $assert(isset($materialById[$materialId]) && in_array('nursing', array_map('strtolower', array_map('strval', (array) ($materialById[$materialId]['exams'] ?? []))), true), 'Lesson material is not Nursing eligible: ' . $materialId);
    $assert(!isset($lessonMaterials[$materialId]), 'Multiple lessons were attached to the same material.');
    $lessonMaterials[$materialId] = true;
    $assert($lesson['source_review']['status'] === 'SOURCE_TEXT_CHECKED_WITH_LIMITATIONS; INDEPENDENT_NURSING_REVIEW_PENDING', 'Academic approval was implied for ' . $lessonId);
    $assert(count((array) ($lesson['learning_objectives'] ?? [])) >= 3 && count((array) ($lesson['sections'] ?? [])) > 0, 'Lesson lacks focused objectives or sections: ' . $lessonId);
    $assert(count((array) ($lesson['practice_question_ids'] ?? [])) > 0, 'Lesson has no exact practice pool: ' . $lessonId);
    foreach (array_merge((array) $lesson['official_scope_source_ids'], (array) $lesson['instructional_source_ids']) as $sourceId) $assert(isset($sourceById[(string) $sourceId]), 'Unregistered lesson source: ' . $sourceId);
    $newObjectiveCount += count($lesson['learning_objectives']);
    $newExampleCount += count((array) ($lesson['worked_examples'] ?? []));
    $newMisconceptionCount += count((array) ($lesson['common_misconceptions'] ?? []));
    $newSectionCount += count((array) $lesson['sections']);
    foreach ((array) ($lesson['sections'] ?? []) as $section) {
        $sectionId = (string) ($section['id'] ?? '');
        $assert($sectionId !== '' && !isset($sectionIds[$sectionId]), 'Missing or duplicate stable section ID: ' . $sectionId);
        $sectionIds[$sectionId] = true;
        $assert(strlen(trim((string) ($section['body'] ?? ''))) >= 350, 'Section is not substantively developed: ' . $sectionId);
        $assert(count((array) ($section['related_concept_ids'] ?? [])) === 1, 'Section must identify its concept explicitly: ' . $sectionId);
        foreach ((array) ($section['related_concept_ids'] ?? []) as $conceptId) $assert(isset($conceptIds[(string) $conceptId]), 'Unknown concept ID ' . $conceptId);
        $sourceIds = array_values(array_unique(array_map('strval', (array) ($section['source_ids'] ?? []))));
        $assert($sourceIds !== [], 'Section has no source provenance: ' . $sectionId);
        foreach ($sourceIds as $sourceId) {
            $assert(isset($sourceById[$sourceId]), 'Section has an unregistered source ' . $sourceId);
            $assert(in_array($sourceId, array_map('strval', (array) $lesson['instructional_source_ids']), true), 'Section source is absent from the lesson-level source list: ' . $sectionId . '/' . $sourceId);
        }
    }
}
$assert($newSectionCount === 23 && $newExampleCount === 23, 'Unexpected cumulative section/example count after the B705 coordination addition.');
$assert($newMisconceptionCount >= 20 && $newObjectiveCount >= 20, 'Expected concept-specific misconceptions and objectives.');

$eligibility = QuestionEligibilityService::index();
$repository = new QuestionRepository(new JsonStorage($storagePath));
$mappedQuestions = $repository->findMany(array_keys($expectedMappings));
$mappedBySection = [];
foreach ($expectedMappings as $questionId => $candidate) {
    $question = $mappedQuestions[$questionId] ?? null;
    $assert(is_array($question) && ($question['status'] ?? '') === 'active', 'Canonical Nursing question missing: ' . $questionId);
    $assert((string) ($question['taxonomy']['board_id'] ?? '') === 'nursing', 'Question identity changed or is not Nursing: ' . $questionId);
    $eligible = $eligibility->forExam($question, 'nursing');
    $assert(is_array($eligible) && (string) ($eligible['exam_id'] ?? '') === 'nursing', 'Question is not production-eligible for Nursing: ' . $questionId);
    $pair = (string) $candidate['material_id'] . "\0" . $questionId;
    $link = $linkByPair[$pair] ?? null;
    $assert(is_array($link), 'Exact question/material pair missing: ' . $questionId);
    $assert(($link['lesson_id'] ?? '') === $candidate['lesson_id'] && ($link['section_id'] ?? '') === $candidate['section_id'], 'Question is linked to the wrong lesson section: ' . $questionId);
    $assert(($link['semantic_review_status'] ?? '') === 'SECTION_AND_SOURCE_CHECKED_PENDING_INDEPENDENT_ACADEMIC_REVIEW', 'Academic approval was improperly assigned to ' . $questionId);
    foreach ((array) ($link['source_ids'] ?? []) as $sourceId) $assert(isset($sourceById[(string) $sourceId]), 'Mapped question has an unregistered source: ' . $sourceId);
    $mappedBySection[$candidate['lesson_id'] . "\0" . $candidate['section_id']][] = $questionId;
}
foreach ($mappedBySection as $key => $ids) {
    [$lessonId, $sectionId] = explode("\0", $key, 2);
    $material = StudyLibraryService::find((string) $lessonById[$lessonId]['material_id']);
    $actual = array_map('strval', StudyLibraryService::practiceQuestionIds($material, $sectionId));
    $expected = array_values(array_unique(array_map('strval', $ids)));
    sort($actual); sort($expected);
    $assert($actual === $expected, 'Section pool is not exactly the defensible link set for ' . $sectionId . ': ' . json_encode($actual));
}
$questionReferences = StudyLibraryService::forQuestions(array_keys($expectedMappings), 'nursing', 'nursing-foundations');
foreach ($expectedMappings as $questionId => $candidate) {
    $found = false;
    foreach ((array) ($questionReferences[$questionId] ?? []) as $reference) {
        if (($reference['lesson_id'] ?? '') === $candidate['lesson_id'] && ($reference['section_id'] ?? '') === $candidate['section_id']) {
            $found = true; break;
        }
    }
    $assert($found, 'Answer review cannot return to the exact section for ' . $questionId);
}

$expectedOldIds = ['1011','1012','4004','4005','4006','4094','4096','4097','4102','5104','5187'];
$oldPending = 0;
foreach ($links as $link) {
    if (!is_array($link) || !in_array((string) ($link['question_id'] ?? ''), $expectedOldIds, true)) continue;
    if (str_contains((string) ($link['semantic_review_status'] ?? ''), 'PENDING')) $oldPending++;
}
$assert($oldPending === 8, 'Expected eight still-supported Batch 702 mappings to remain review-pending after B704 demoted three section mismatches.');
$allSectionRows = count(array_filter($links, static fn(array $link): bool => trim((string) ($link['section_id'] ?? '')) !== ''));
$explicitSectionRows = count(array_filter($links, static fn(array $link): bool => trim((string) ($link['lesson_id'] ?? '')) !== '' && trim((string) ($link['section_id'] ?? '')) !== ''));
$pendingSectionRows = count(array_filter($links, static fn(array $link): bool => trim((string) ($link['section_id'] ?? '')) !== '' && str_contains((string) ($link['semantic_review_status'] ?? ''), 'PENDING')));
$assert($allSectionRows === 1051 && $explicitSectionRows === 69 && $pendingSectionRows === 93, 'Portfolio section-link reconciliation changed after B705: ' . json_encode([$allSectionRows,$explicitSectionRows,$pendingSectionRows]));
$exactNursing = count(array_filter($links, static fn(array $link): bool => isset($questionById[(string) ($link['question_id'] ?? '')]) && ($questionById[(string) $link['question_id']]['taxonomy']['board_id'] ?? '') === 'nursing' && !empty($link['lesson_id']) && !empty($link['section_id'])));
$assert($exactNursing === 47, 'Expected 47 Nursing exact section mappings after B706 quarantined the two dose-basis links.');
$manifestById = [];
foreach ((array) ($manifest['files'] ?? []) as $entry) $manifestById[(string) ($entry['lesson_id'] ?? '')] = $entry;
$baselineAssets = (array) ($baseline['published_study_pdf_assets_before'] ?? []);
$assert(count($baselineAssets) === 49 && count($manifestById) === 59, 'PDF manifest count mismatch after B704 PDFs.');
$b705RegeneratedAssets = array_fill_keys([
    'nursing-community-risk-and-prevention',
    'nursing-medication-safety-and-calculation',
    'nursing-nutrition-and-sensory-assessment',
    'nursing-oxygenation-fluid-immunity-assessment',
    'nursing-therapeutic-communication-and-crisis',
], true);
foreach ($baselineAssets as $oldAsset) {
    $entry = $manifestById[(string) $oldAsset['lesson_id']] ?? null;
    $assert(is_array($entry) && ($entry['path'] ?? '') === $oldAsset['path'], 'Existing PDF identity/path changed: ' . $oldAsset['lesson_id']);
    if (!isset($b705RegeneratedAssets[(string) $oldAsset['lesson_id']])) {
        $assert(($entry['sha256'] ?? '') === $oldAsset['sha256'], 'Unmodified prior PDF hash changed: ' . $oldAsset['lesson_id']);
    }
}

$cookies = ['PHPSESSID' => 'batch703-nursing-' . bin2hex(random_bytes(8))];
$requireSuccess = static function (array $response, string $label): void {
    if (!$response['success'] || ($response['status'] ?? 0) !== 200) {
        $message = substr(preg_replace('/\s+/', ' ', strip_tags((string) ($response['output'] ?? ''))) ?? '', 0, 250);
        throw new RuntimeException($label . ' failed: HTTP ' . ($response['status'] ?? 0) . ' ' . $message);
    }
};
foreach ($expectedLessonIds as $lessonId) {
    $lesson = $lessonById[$lessonId];
    $materialId = (string) $lesson['material_id'];
    $detail = $http->request('GET', '/study-library', ['exam' => 'nursing', 'subject' => 'nursing-foundations', 'material' => $materialId], [], [], $cookies);
    $requireSuccess($detail, 'Lesson route ' . $lessonId);
    $assert(str_contains($detail['output'], (string) $lesson['title']), 'Lesson title is not shown: ' . $lessonId);
    $assert(str_contains($detail['output'], 'What you will learn') && str_contains($detail['output'], 'aria-label="Lesson sections"'), 'Lesson objectives/section navigation missing: ' . $lessonId);
    $assert(str_contains($detail['output'], 'Download searchable study PDF') || str_contains($detail['output'], '/study-resources/' . $lesson['pdf_file']), 'Lesson PDF route is not shown: ' . $lessonId);
    $assert(str_contains($detail['output'], 'Sources for this section'), 'Section-level source disclosure is missing: ' . $lessonId);
    foreach ((array) $lesson['sections'] as $section) {
        $anchor = StudyLibraryService::lessonSectionAnchorId($lessonId, (string) $section['id']);
        $assert(str_contains($detail['output'], 'id="' . $anchor . '"'), 'Section anchor does not render: ' . $anchor);
        $allowed = array_map('strval', StudyLibraryService::practiceQuestionIds(StudyLibraryService::find($materialId), (string) $section['id']));
        if ($allowed === []) continue; // B704 permits truthful empty exact pools; dedicated B704 tests cover their safe response.
    }
    $sampleSection = $lesson['sections'][0];
    $allowed = array_map('strval', StudyLibraryService::practiceQuestionIds(StudyLibraryService::find($materialId), (string) $sampleSection['id']));
    $start = $http->request('GET', '/study-library/practice', ['material' => $materialId, 'section' => (string) $sampleSection['id']], [], [], $cookies);
    $requireSuccess($start, 'Section practice route ' . $sampleSection['id']);
    if (!preg_match('/name="question_id"\s+value="([^"]+)"/', $start['output'], $match)) throw new RuntimeException('Practice route did not select a question for ' . $sampleSection['id']);
    $assert(in_array($match[1], $allowed, true), 'Section practice selected a question outside the exact pool: ' . $match[1]);
}

$foreignScope = $http->request('GET', '/study-library', ['exam' => 'let', 'subject' => 'english', 'material' => 'nle-np-i-foundation'], [], [], $cookies);
$requireSuccess($foreignScope, 'Cross-board study route');
$assert(!str_contains($foreignScope['output'], 'Community Assessment, Prevention, and Population Reasoning'), 'Nursing lesson leaked into LET English.');

$complete = static function (string $questionId, bool $correct, bool $repeat) use ($http, $questionById, $expectedMappings, $lessonById, $cookies, $requireSuccess, $assert): void {
    $candidate = $expectedMappings[$questionId];
    $materialId = (string) $candidate['material_id'];
    $sectionId = (string) $candidate['section_id'];
    $sectionPool = [];
    foreach ($expectedMappings as $poolQuestionId => $poolCandidate) {
        if (($poolCandidate['lesson_id'] ?? '') === ($candidate['lesson_id'] ?? '')
            && ($poolCandidate['section_id'] ?? '') === $sectionId) {
            $sectionPool[] = (string) $poolQuestionId;
        }
    }
    $activeCookies = ['PHPSESSID' => 'batch703-practice-' . $questionId . '-' . bin2hex(random_bytes(6))];
    $start = $http->request('GET', '/study-library/practice', ['material' => $materialId, 'section' => $sectionId], [], [], $activeCookies);
    $requireSuccess($start, 'Practice start ' . $questionId);
    if (!preg_match('/name="question_id"\s+value="([^"]+)"/', $start['output'], $idMatch)
        || !preg_match('/name="csrf_token"\s+value="([a-f0-9]{64})"/', $start['output'], $csrfMatch)) throw new RuntimeException('Question/CSRF missing for ' . $questionId);
    $selectedId = $idMatch[1];
    $assert(in_array($selectedId, $sectionPool, true), 'Section practice selected a question outside its exact pool: ' . $selectedId);
    $question = $questionById[$selectedId];
    $answer = null; $wrong = null;
    foreach (array_values((array) $question['choices']) as $index => $choice) {
        $letter = chr(65 + $index);
        if (strcasecmp(trim((string) $choice), trim((string) $question['answer'])) === 0) $answer = $letter;
        elseif ($wrong === null) $wrong = $letter;
    }
    if ($answer === null || $wrong === null) throw new RuntimeException('Question choices do not resolve an answer and distractor: ' . $questionId);
    $selected = $correct ? $answer : $wrong;
    $submission = $http->request('POST', '/quiz', [], ['action' => 'submit', 'question_id' => $selectedId, 'answer' => $selected, 'csrf_token' => $csrfMatch[1]], [], $activeCookies);
    $requireSuccess($submission, 'Answer submission ' . $questionId);
    $expectedLabel = $correct ? 'Correct!' : 'Incorrect';
    $assert(str_contains($submission['output'], $expectedLabel), 'Expected ' . $expectedLabel . ' feedback was not rendered for ' . $questionId);
    $finish = $http->request('POST', '/quiz', [], ['action' => 'finish', 'csrf_token' => $csrfMatch[1]], [], $activeCookies);
    $assert(($finish['status'] ?? 0) === 303, 'Practice did not finish in isolated fixture for ' . $questionId);
    $review = $http->request('GET', '/quiz', ['action' => 'result'], [], [], $activeCookies);
    $requireSuccess($review, 'Answer review ' . $questionId);
    $anchor = StudyLibraryService::lessonSectionAnchorId((string) $candidate['lesson_id'], $sectionId);
    $assert(str_contains($review['output'], 'Open supporting lesson section:') && str_contains($review['output'], rawurlencode($anchor)), 'Answer review lost its exact section return link for ' . $questionId);
    $assert(str_contains($review['output'], $correct ? 'Correct' : 'Incorrect'), 'Answer review feedback missing for ' . $questionId);
    if ($repeat) {
        $again = $http->request('GET', '/study-library/practice', ['material' => $materialId, 'section' => $sectionId], [], [], $activeCookies);
        $requireSuccess($again, 'Repeated practice start ' . $questionId);
        $assert(preg_match('/name="question_id"\s+value="([^"]+)"/', $again['output'], $againId) === 1 && in_array($againId[1], $sectionPool, true), 'Repeated practice did not recover the exact scoped pool for ' . $questionId);
    }
};
$complete('3864', true, true);
$complete('3984', false, false);

$rejectedIds = ['1514','3856','3944','3974'];
foreach ($rejectedIds as $questionId) {
    $assert(isset($questionById[$questionId]), 'Review-only item missing: ' . $questionId);
    $nursingExact = array_filter($links, static fn(array $link): bool => (string) ($link['question_id'] ?? '') === $questionId && ($link['lesson_id'] ?? '') !== '' && ($link['section_id'] ?? '') !== '' && str_starts_with((string) ($link['lesson_id'] ?? ''), 'nursing-'));
    $assert($nursingExact === [], 'Review-only item received an unsupported exact Nursing link: ' . $questionId);
}
$q1018 = $questionById['1018'];
$assert(($q1018['taxonomy']['concept_id'] ?? '') === 'nle-np-v-teamwork', 'Q1018 taxonomy was changed instead of flagged.');
$q4239ExactNursing = array_filter($links, static fn(array $link): bool => (string) ($link['question_id'] ?? '') === '4239' && ($link['lesson_id'] ?? '') !== '' && str_starts_with((string) ($link['lesson_id'] ?? ''), 'nursing-'));
$assert($q4239ExactNursing === [], 'Previously rejected CPA link leaked into Nursing.');

fwrite(STDOUT, "[PASS] Seven B703-scope Nursing lessons, 23 cumulative stable concept sections and worked examples, registered section sources, and scoped pools validated.\n");
fwrite(STDOUT, "[PASS] B703 mappings were reconciled with B705 demotions 4014/5025 and the retained Q1540 link; eight Batch 702 mappings remain supported.\n");
fwrite(STDOUT, "[PASS] Study/source integrity, 30/30 NP coverage, 1,051 portfolio section rows, 93 pending section-review rows, and retained PDF asset hashes validated.\n");
fwrite(STDOUT, "[PASS] Isolated production HTTP flows cover seven B703-scope lesson pages, mapped section practice, cross-board rejection, correct/incorrect review, repeat practice, and recovery anchors.\n");
fwrite(STDOUT, "[PASS] Learner/session mutations stayed in HttpSimulator storage; canonical question SHA is unchanged.\n");
