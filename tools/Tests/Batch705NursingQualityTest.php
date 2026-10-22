<?php

declare(strict_types=1);

putenv('APP_ENV=production');
putenv('APP_DEBUG=false');
putenv('APP_TIMEZONE=UTC');
putenv('DB_DRIVER=json');

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();
require_once __DIR__ . '/Support/StaticStorageFixture.php';
$root = dirname(__DIR__, 2);
putenv('APP_STORAGE_PATH=' . StaticStorageFixture::create($root, 'batch705-quality-regression'));
require dirname(__DIR__, 2) . '/bootstrap/app.php';

use App\Core\App;
use App\Services\Question\QuestionEligibilityService;
use App\Services\Study\SourceRegistryService;
use App\Services\Study\StudyLibraryService;

$assertions = 0;
$failures = [];
$assert = static function (bool $condition, string $message) use (&$assertions, &$failures): void {
    $assertions++;
    if (!$condition) $failures[] = $message;
};
$read = static function (string $name) use ($root): array {
    $decoded = json_decode((string) file_get_contents($root . '/storage/' . $name . '.json'), true);
    if (!is_array($decoded)) throw new RuntimeException('Invalid JSON storage file: ' . $name);
    return $decoded;
};

$questions = $read('questions');
$lessons = $read('study-lessons');
$links = $read('study-question-links');
$sources = $read('sources');
$concepts = $read('taxonomy/concepts');
$manifest = json_decode((string) file_get_contents($root . '/public/study-resources/manifest.json'), true);
$assert(is_array($manifest) && is_array($manifest['files'] ?? null), 'Study PDF manifest is invalid.');
$sourceCheck = SourceRegistryService::validateSnapshot($sources);
$assert(($sourceCheck['valid'] ?? false) === true, 'Source registry validation failed: ' . json_encode($sourceCheck['issues'] ?? []));

$questionById = [];
foreach ($questions as $question) if (is_array($question) && isset($question['id'])) $questionById[(string) $question['id']] = $question;
$lessonById = [];
$sectionById = [];
foreach ($lessons as $lesson) {
    if (!is_array($lesson) || trim((string) ($lesson['id'] ?? '')) === '') continue;
    $lessonById[(string) $lesson['id']] = $lesson;
    foreach ((array) ($lesson['sections'] ?? []) as $section) {
        if (!is_array($section) || trim((string) ($section['id'] ?? '')) === '') continue;
        $sectionById[(string) $section['id']] = ['lesson' => $lesson, 'section' => $section];
    }
}
$sourceById = [];
foreach ($sources as $source) if (is_array($source) && isset($source['id'])) $sourceById[(string) $source['id']] = $source;
$conceptIds = array_fill_keys(array_map('strval', array_column($concepts, 'id')), true);

$eligibility = QuestionEligibilityService::index();
$nursingQuestions = [];
foreach ($questions as $question) {
    if (!is_array($question) || ($question['status'] ?? '') !== 'active') continue;
    if (QuestionEligibilityService::forExam($question, 'nursing', $eligibility) !== null) {
        $nursingQuestions[(string) ($question['id'] ?? '')] = $question;
    }
}
$assert(count($nursingQuestions) === 878, 'Expected 878 active Nursing-eligible canonical questions; got ' . count($nursingQuestions));
$questionHash = hash_file('sha256', $root . '/storage/questions.json');
$assert($questionHash === '73cdc775cd7240b57898868d67902d833db1171f947c8dcc89081ddaf46fb4be', 'Canonical question bytes changed: ' . $questionHash);

$sharedIds = array_map('strval', [31, 32, 33, 34, 35, 36, 37, 38, 39, 40, 106, 107, 108, 109, 110, 111, 112, 113, 114, 115]);
foreach ($sharedIds as $id) {
    $question = $questionById[$id] ?? null;
    $assert(is_array($question), 'Shared-science identity is missing: ' . $id);
    if (!is_array($question)) continue;
    $assert(QuestionEligibilityService::forExam($question, 'nursing', $eligibility) === null, 'Shared-science question leaked into Nursing: ' . $id);
    $assert(QuestionEligibilityService::forExam($question, 'let', $eligibility) !== null, 'LET shared-science eligibility was lost: ' . $id);
    $assert(QuestionEligibilityService::forExam($question, 'civil-service', $eligibility) !== null, 'Civil-service shared-science eligibility was lost: ' . $id);
}

$exactRows = [];
$exactIds = [];
foreach ($links as $link) {
    if (!is_array($link) || empty($link['lesson_id']) || empty($link['section_id'])) continue;
    $lesson = $lessonById[(string) $link['lesson_id']] ?? null;
    if (!is_array($lesson) || strtolower((string) ($lesson['board_id'] ?? '')) !== 'nursing') continue;
    $questionId = (string) ($link['question_id'] ?? '');
    $question = $questionById[$questionId] ?? null;
    $section = $sectionById[(string) $link['section_id']] ?? null;
    $exactRows[] = $link;
    $exactIds[$questionId] = true;
    $assert(is_array($question) && ($question['status'] ?? '') === 'active', 'Exact link references a missing/inactive question: ' . $questionId);
    if (is_array($question)) {
        $assert(QuestionEligibilityService::forExam($question, 'nursing', $eligibility) !== null, 'Exact link references a non-Nursing-eligible question: ' . $questionId);
    }
    $assert(is_array($section) && ($section['lesson']['id'] ?? '') === ($link['lesson_id'] ?? ''), 'Exact link has an invalid section owner: ' . $questionId);
    $assert(($link['semantic_review_status'] ?? '') === 'SECTION_AND_SOURCE_CHECKED_PENDING_INDEPENDENT_ACADEMIC_REVIEW', 'Exact link was promoted or has an unknown status: ' . $questionId);
    $assert(in_array($questionId, array_map('strval', (array) ($lesson['practice_question_ids'] ?? [])), true), 'Exact link is absent from the lesson practice pool: ' . $questionId);
    foreach (array_map('strval', (array) ($link['source_ids'] ?? [])) as $sourceId) {
        $assert(isset($sourceById[$sourceId]), 'Exact link has an unregistered source: ' . $questionId . '/' . $sourceId);
    }
}
$assert(count($exactRows) === 49, 'Expected 49 Nursing exact section mappings; got ' . count($exactRows));
$assert(count($exactIds) === 49, 'Expected each Nursing exact mapping to be a unique question.');

$mappingQuestions = array_keys($exactIds);
$studyRefs = StudyLibraryService::forQuestions($mappingQuestions, 'nursing', 'nursing-foundations');
foreach ($exactRows as $link) {
    $questionId = (string) $link['question_id'];
    $anchor = StudyLibraryService::lessonSectionAnchorId((string) $link['lesson_id'], (string) $link['section_id']);
    $found = false;
    foreach ((array) ($studyRefs[$questionId] ?? []) as $ref) {
        if (($ref['lesson_id'] ?? '') === $link['lesson_id'] && ($ref['section_id'] ?? '') === $link['section_id'] && str_contains((string) ($ref['url'] ?? ''), $anchor)) $found = true;
    }
    $assert($found, 'Answer-review reference does not resolve to exact section: ' . $questionId);
}

foreach (['4004', '4005', '4006', '4014', '5025'] as $questionId) {
    $rows = array_values(array_filter($links, static fn(array $link): bool => (string) ($link['question_id'] ?? '') === $questionId));
    $nursingExact = array_filter($rows, static fn(array $link): bool => !empty($link['lesson_id']) && !empty($link['section_id']) && strtolower((string) ($lessonById[(string) $link['lesson_id']]['board_id'] ?? '')) === 'nursing');
    $sourceOnly = array_filter($rows, static fn(array $link): bool => !empty($link['source_ids']) && empty($link['section_id']));
    $assert($nursingExact === [], 'Previously demoted/source-limited exact relationship returned: ' . $questionId);
    $assert($sourceOnly !== [], 'Demotion lost its source-only audit/reference row: ' . $questionId);
}
foreach (['967', '896', '4090'] as $questionId) {
    $assert(!isset($exactIds[$questionId]), 'Held question unexpectedly has an exact section relationship: ' . $questionId);
}
$assert(!isset($nursingQuestions['4239']), 'Non-Nursing CPA question entered the Nursing index.');

$newSections = [
    'b705-coordination-baseline-and-change' => 'nle-np-iv-coordination',
    'b705-medication-weight-based-dose' => 'nle-np-iv-medication',
];
foreach ($newSections as $sectionId => $conceptId) {
    $owned = $sectionById[$sectionId] ?? null;
    $assert(is_array($owned), 'Batch 705 section is missing: ' . $sectionId);
    if (!is_array($owned)) continue;
    $lesson = $owned['lesson'];
    $section = $owned['section'];
    $assert(($lesson['board_id'] ?? '') === 'nursing' && ($lesson['status'] ?? '') === 'published', 'Batch 705 section is not in a published Nursing lesson: ' . $sectionId);
    $assert(strlen(trim((string) ($section['body'] ?? ''))) >= 350, 'Batch 705 section is not substantive: ' . $sectionId);
    $assert(in_array($conceptId, array_map('strval', (array) ($section['related_concept_ids'] ?? [])), true) && isset($conceptIds[$conceptId]), 'Batch 705 section has invalid concept relationship: ' . $sectionId);
    $assert((bool) ($section['source_ids'] ?? []), 'Batch 705 section has no claim-level sources: ' . $sectionId);
    foreach (array_map('strval', (array) ($section['source_ids'] ?? [])) as $sourceId) {
        $assert(isset($sourceById[$sourceId]), 'Batch 705 section has an unregistered source: ' . $sectionId . '/' . $sourceId);
    }
    $assert(str_contains((string) ($lesson['source_review']['status'] ?? ''), 'INDEPENDENT_NURSING_REVIEW_PENDING'), 'Batch 705 lesson was promoted beyond pending review: ' . $sectionId);
}

$inventoryPath = $root . '/docs/reports/batch-705-question-quality-audit.json';
$inventory = json_decode((string) file_get_contents($inventoryPath), true);
$assert(is_array($inventory), 'Batch 705 question quality inventory is invalid JSON.');
$assert(($inventory['summary']['active_nursing_eligible_questions'] ?? 0) === 878, 'Question inventory count does not match production resolver.');
$assert(($inventory['summary']['canonical_question_sha256'] ?? '') === $questionHash, 'Question inventory records a different canonical question hash.');
$assert(count((array) ($inventory['questions'] ?? [])) === 878, 'Question inventory does not contain every eligible canonical question.');
$cohort = (array) ($inventory['deep_review_cohort']['items'] ?? []);
$cohortIds = array_map(static fn(array $item): string => (string) ($item['question_snapshot']['QUESTION_ID'] ?? ''), $cohort);
$assert(count($cohort) === 93 && count(array_unique($cohortIds)) === 93, 'Deep-review cohort is not 93 unique complete question records.');
$assert(($inventory['deep_review_cohort']['reviewed_unique_questions'] ?? 0) === 93, 'Cohort review count is inconsistent.');
foreach ($cohort as $item) {
    $snapshot = (array) ($item['question_snapshot'] ?? []);
    $canonical = $questionById[(string) ($snapshot['QUESTION_ID'] ?? '')] ?? null;
    $assert(is_array($canonical), 'Deep-review item is absent from canonical question storage.');
    if (!is_array($canonical)) continue;
    $assert(($snapshot['STEM'] ?? null) === ($canonical['question'] ?? null), 'Deep-review stem differs from canonical item: ' . ($snapshot['QUESTION_ID'] ?? ''));
    $assert(($snapshot['OPTIONS'] ?? null) === ($canonical['choices'] ?? null), 'Deep-review choices differ from canonical item: ' . ($snapshot['QUESTION_ID'] ?? ''));
    $assert(($snapshot['ANSWER_KEY'] ?? null) === ($canonical['answer'] ?? null), 'Deep-review key differs from canonical item: ' . ($snapshot['QUESTION_ID'] ?? ''));
    $assert(($snapshot['RATIONALE'] ?? null) === ($canonical['explanation'] ?? null), 'Deep-review rationale differs from canonical item: ' . ($snapshot['QUESTION_ID'] ?? ''));
    $assert(($item['ACADEMIC_REVIEW_STATUS'] ?? '') === 'NOT_REVIEWED' && ($item['REVIEWER_DECISION'] ?? '') === 'NOT_REVIEWED', 'Audit record claims independent review: ' . ($snapshot['QUESTION_ID'] ?? ''));
}

$mappingAudit = json_decode((string) file_get_contents($root . '/docs/reports/batch-705-nursing-mapping-audit.json'), true);
$assert(is_array($mappingAudit) && count((array) ($mappingAudit['mappings'] ?? [])) === 49, 'Mapping audit does not contain 49 current exact relationships.');
$assert(($mappingAudit['summary']['existing_links_demoted_this_batch'] ?? 0) === 2, 'The Q4014/Q5025 demotions are not recorded.');
$assert(($mappingAudit['summary']['all_current_exact_links_pending_review'] ?? false) === true, 'Current mappings are not marked independently review-pending.');
$assert(count(array_unique(array_map(static fn(array $row): string => (string) ($row['QUESTION_ID'] ?? ''), (array) ($mappingAudit['mappings'] ?? [])))) === 49, 'Mapping audit repeats a current question identity.');
$assert(count(array_filter((array) ($mappingAudit['mappings'] ?? []), static fn(array $row): bool => ($row['ACADEMIC_REVIEW_STATUS'] ?? '') === 'NOT_REVIEWED')) === 49, 'Mapping audit has a non-pending academic decision.');
$claimLedger = json_decode((string) file_get_contents($root . '/docs/reports/batch-705-source-claim-ledger.json'), true);
$claimRecords = (array) ($claimLedger['records'] ?? []);
$batch705ClaimRecords = array_values(array_filter($claimRecords, static fn(array $record): bool => ($record['BATCH_ADDITION'] ?? null) === 705));
$assert(count($batch705ClaimRecords) === 12, 'Batch 705 source-to-claim ledger omits section or exact-mapping evidence.');
foreach ($batch705ClaimRecords as $record) {
    foreach (['SECTION_ID','CONCEPT_ID','SOURCE_ID','SOURCE_TITLE','PUBLISHER','YEAR_OR_EDITION','ACCESS_STATUS','ACCESS_METHOD','SOURCE_TEXT_VERIFIED','SPECIFIC_CLAIM_SUPPORTED','EVIDENCE_LOCATION','JURISDICTION','CURRENTNESS_LIMIT','REVIEW_REQUIRED'] as $field) {
        $assert(array_key_exists($field, $record) && $record[$field] !== '', 'Claim ledger record is missing ' . $field . ': ' . ($record['SOURCE_ID'] ?? 'unknown'));
    }
    $assert(($record['REVIEW_REQUIRED'] ?? false) === true, 'New claim was promoted beyond review-required status.');
}
$queue = (string) file_get_contents($root . '/docs/reports/batch-705-academic-review-queue.md');
$assert(str_contains($queue, 'APPROVED_BY_AUTHORIZED_REVIEWER') && str_contains($queue, 'NOT_REVIEWED'), 'Reviewer decision workflow is incomplete.');
$assert(substr_count($queue, '| exact mapping |') === 49, 'Reviewer queue does not expose all current mappings.');
$coverageAfter = json_decode((string) file_get_contents($root . '/docs/reports/batch-705-nursing-coverage-after.json'), true);
$assert(($coverageAfter['nursing_structural'] ?? '') === '30/40' && ($coverageAfter['np_i_v_structural'] ?? '') === '30/30', 'Coverage report does not reconcile B705 structural result.');
$assert(($coverageAfter['global_structural'] ?? '') === '407/687', 'Coverage report does not use current catalog denominator.');
$baseline = json_decode((string) file_get_contents($root . '/docs/reports/batch-704-nursing-coverage-before.json'), true);
foreach ((array) ($baseline['mutable_state_before_testing'] ?? []) as $relative => $state) {
    $path = $root . '/' . $relative;
    if (($state['status'] ?? '') === 'PRESENT') $assert(is_file($path) && hash_file('sha256', $path) === ($state['sha256'] ?? ''), 'Protected learner/review state changed during Batch 705: ' . $relative);
    elseif (($state['status'] ?? '') === 'ABSENT_IN_B704_BRANCH_CHECKOUT') $assert(!is_file($path), 'Batch test unexpectedly created tracked learner state: ' . $relative);
}

$manifestByLesson = [];
foreach ((array) ($manifest['files'] ?? []) as $entry) {
    if (!is_array($entry)) continue;
    $lessonId = (string) ($entry['lesson_id'] ?? '');
    $manifestByLesson[$lessonId] = $entry;
    $path = $root . '/' . ltrim((string) ($entry['path'] ?? ''), '/');
    $assert(is_file($path), 'Manifest PDF asset is missing: ' . $lessonId);
    if (is_file($path)) $assert(hash_file('sha256', $path) === (string) ($entry['sha256'] ?? ''), 'Manifest PDF checksum mismatch: ' . $lessonId);
}
$assert(count($manifestByLesson) === 59, 'Expected the 59 existing lesson PDF identities to remain after regeneration, got ' . count($manifestByLesson));
foreach (['nursing-nutrition-and-sensory-assessment', 'nursing-medication-safety-and-calculation', 'nursing-oxygenation-fluid-immunity-assessment', 'nursing-therapeutic-communication-and-crisis', 'nursing-community-risk-and-prevention'] as $lessonId) {
    $assert(isset($manifestByLesson[$lessonId]), 'Affected existing Nursing PDF identity is missing: ' . $lessonId);
}

if ($failures !== []) {
    fwrite(STDERR, "Batch705NursingQualityTest FAILED (" . count($failures) . "/$assertions)\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}
echo "Batch705NursingQualityTest PASS ($assertions assertions; 878 Nursing-eligible questions; 49 exact links; shared-science isolation; pending review; manifest hashes)\n";
