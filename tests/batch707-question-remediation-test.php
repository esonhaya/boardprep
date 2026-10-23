<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Core/Autoloader.php';
App\Core\Autoloader::register();
require_once dirname(__DIR__) . '/app/Services/Quiz/Selection/QuestionPoolFilter.php';

use App\Services\Question\Quality\StudyRelationshipSummary;
use App\Services\RepositoryHealth\Engine\RepositoryHealthEngine;
use App\Services\RepositoryHealth\SourceUrlHealthSnapshot;
use App\Services\Study\SourceRegistryService;

$root = dirname(__DIR__);
$fail = static function (string $message): never {
    fwrite(STDERR, 'BATCH707_REMEDIATION_FAIL=' . $message . PHP_EOL);
    exit(1);
};
$read = static function (string $relative) use ($root): array {
    $value = json_decode((string) file_get_contents($root . '/' . $relative), true, 512, JSON_THROW_ON_ERROR);
    return is_array($value) ? $value : [];
};
$hash = static fn(string $relative): string => hash_file('sha256', $root . '/' . $relative);

$audit = $read('docs/reports/batch-707-question-integrity-issues.json');
$questions = $read('storage/questions.json');
$sources = $read('storage/sources.json');
$links = $read('storage/study-question-links.json');
$materials = $read('storage/study-materials.json');
$lessons = $read('storage/study-lessons.json');
$urlSnapshots = $read('storage/source-url-health.json');
$reuseOverrides = $read('storage/question-reuse-overrides.json');
$questionById = [];
$sourceById = [];
$materialById = [];
foreach ($questions as $question) $questionById[(string) ($question['id'] ?? '')] = $question;
foreach ($sources as $source) $sourceById[(string) ($source['id'] ?? '')] = $source;
foreach ($materials as $material) $materialById[(string) ($material['id'] ?? '')] = $material;

if ($hash('storage/questions.json') !== ($audit['canonical_question_sha256'] ?? '')) $fail('Canonical question bytes changed from the read-only audit baseline.');
if ($hash('storage/study-question-links.json') !== 'fa9daec05391e575e5029ac717a28969414e4c6df87c540bf1319d612bd59de6') $fail('Question-to-study links changed during source remediation.');
foreach ([
    'storage/attempts.json' => 'e968fd3e8a391065ac2314ab9bcff932fc572cbe6024e16b40569b694bbed84b',
    'storage/weakness.json' => '37517e5f3dc66819f61f5a7bb8ace1921282415f10551d2defa5c3eb0985b570',
    'storage/question-statistics.json' => '4f53cda18c2baa0c0354bb5f9a3ecbe5ed12ab4d8e11ba873c2f11161202b945',
    'storage/flashcard-exclusions.json' => '236975ba94ef72c223ebe7f78a4725088ca53b7c20395edb52f39e5d5e2f7717',
    'storage/question-eligibility.json' => '611f01ca29421c0c890c351c503ab262e87bc551269d78427b9a0889260933a4',
    'storage/concept-derived-eligibility.json' => 'ea9357ec1db3f710a1ae010338b216d843dab6c0aed3495a3b869c2aee939c61',
] as $path => $expectedHash) {
    if (!is_file($root . '/' . $path) || !hash_equals($expectedHash, $hash($path))) $fail('Protected learner or eligibility data changed: ' . $path);
}

$q6403 = $questionById['6403'] ?? null;
if (!is_array($q6403) || (string) ($q6403['answer'] ?? '') !== 'AB-0072') $fail('Q6403 canonical answer changed; its disputed key must remain held.');
$thirdChoice = (string) ($q6403['choices'][2] ?? '');
if ($thirdChoice !== 'AB-0072.' || !str_contains((string) ($q6403['explanation'] ?? ''), 'punctuation')) $fail('Q6403 punctuation contradiction is no longer represented by the canonical item.');
$currentScoring = QuestionScoreEvaluator::evaluate($q6403, 'C');
if (($currentScoring['correct'] ?? true) !== false) $fail('Q6403 currently scores the punctuation-preserving choice as correct; expected the known contradiction.');
$authorizedFixture = $q6403;
$authorizedFixture['answer'] = $thirdChoice;
$proposedScoring = QuestionScoreEvaluator::evaluate($authorizedFixture, 'C');
if (($proposedScoring['correct'] ?? false) !== true) $fail('A proposed owner-authorized key using the punctuation-preserving choice does not score as expected.');
$q6403Hold = array_values(array_filter($reuseOverrides, static fn(array $override): bool => (string) ($override['question_id'] ?? '') === '6403'));
if (count($q6403Hold) !== 1
    || ($q6403Hold[0]['board_id'] ?? '') !== 'civil-service'
    || ($q6403Hold[0]['override_type'] ?? '') !== 'EXCLUDE'
    || !str_contains((string) ($q6403Hold[0]['reason'] ?? ''), 'exact-punctuation')
    || (($q6403Hold[0]['provenance'][0]['verified_status'] ?? '') !== 'REVIEW_REQUIRED')) {
    $fail('Q6403 temporary review-required exclusion is missing, broad, or presented as approved.');
}
$eligibility = $read('storage/question-eligibility.json');
$derivedEligibility = $read('storage/concept-derived-eligibility.json');
$selection = new SelectionRequest('*', null, [], 1, exam: 'civil-service');
$baselineIndex = new App\Services\Question\QuestionEligibilityIndex($eligibility, [], $derivedEligibility, []);
$holdIndex = new App\Services\Question\QuestionEligibilityIndex($eligibility, [], $derivedEligibility, $reuseOverrides);
if (count(QuestionPoolFilter::filter([$q6403], $selection, $baselineIndex)) !== 1) $fail('Q6403 baseline eligibility fixture no longer reproduces its active practice placement.');
if (QuestionPoolFilter::filter([$q6403], $selection, $holdIndex) !== []
    || $holdIndex->forExam($q6403, 'civil-service') !== null) {
    $fail('Q6403 review hold does not exclude it from new Civil Service practice through the production pool filter.');
}
foreach ($read('storage/boards.json') as $board) {
    $boardId = (string) ($board['id'] ?? '');
    if ($boardId === '' || $boardId === 'civil-service') continue;
    if (($baselineIndex->forExam($q6403, $boardId) !== null) !== ($holdIndex->forExam($q6403, $boardId) !== null)) {
        $fail('Q6403 temporary hold changed eligibility outside Civil Service: ' . $boardId);
    }
}
if (App\Services\Question\QuestionReuseMetadataService::validateProvenance($q6403Hold[0]['provenance'] ?? null) !== []) $fail('Q6403 hold provenance does not satisfy the existing review-required provenance contract.');
$attempts = $read('storage/attempts.json');
$q6403AttemptCount = 0;
foreach ($attempts as $attempt) {
    foreach ((array) ($attempt['questions'] ?? []) as $attemptQuestion) {
        if (is_array($attemptQuestion) && (string) ($attemptQuestion['question_id'] ?? '') === '6403') $q6403AttemptCount++;
    }
}
if ($q6403AttemptCount !== 0) $fail('Historical Q6403 attempts exist and must be checked before any owner-approved rekeying.');

foreach (['1925', '1926', '1927'] as $id) {
    if (!isset($questionById[$id]) || !in_array('bir-ease-of-paying-taxes-act', (array) ($questionById[$id]['source_ids'] ?? []), true)) $fail('Canonical direct source identity changed for Q' . $id . '.');
}
if (!isset($sourceById['bir-ease-of-paying-taxes-act']) || ($sourceById['bir-ease-of-paying-taxes-act']['url'] ?? '') !== 'https://lawphil.net/statutes/repacts/ra2024/ra_11976_2024.html') $fail('Exact RA 11976 source identity is not registered.');
foreach (['bir-ease-of-paying-taxes-act', 'philippines-negotiable-instruments-law'] as $sourceId) {
    if (($sourceById[$sourceId]['source_text_verified'] ?? false) !== true
        || ($sourceById[$sourceId]['human_review_status'] ?? '') !== 'REVIEW_REQUIRED') {
        $fail('Full-text access and independent review status are not distinguished for ' . $sourceId . '.');
    }
}
if (($sourceById['philippines-ease-of-paying-taxes-act']['url'] ?? '') === ($sourceById['bir-ease-of-paying-taxes-act']['url'] ?? '')) $fail('The EOPT statute was conflated with BIR RR No. 7-2024.');
$invalidDirect = [];
foreach ($questions as $question) foreach ((array) ($question['source_ids'] ?? []) as $sourceId) if (!isset($sourceById[(string) $sourceId])) $invalidDirect[] = [(string) ($question['id'] ?? ''), (string) $sourceId];
if ($invalidDirect !== []) $fail('Unresolved direct question source IDs remain: ' . json_encode($invalidDirect));
if (!(SourceRegistryService::validateSnapshot($sources)['valid'] ?? false)) $fail('Source registry schema/identity validation failed.');

foreach (['1883', '1919'] as $id) {
    $link = null;
    foreach ($links as $candidate) if ((string) ($candidate['question_id'] ?? '') === $id && in_array('philippines-negotiable-instruments-law', (array) ($candidate['source_ids'] ?? []), true)) $link = $candidate;
    if (!is_array($link) || !isset($sourceById['philippines-negotiable-instruments-law'])) $fail('Q' . $id . ' statutory source relationship is missing.');
}
$urlAudit = SourceUrlHealthSnapshot::inspect(
    $urlSnapshots,
    $sources,
    new DateTimeImmutable('2026-10-23T12:00:00Z'),
    SourceUrlHealthSnapshot::affectedQuestionsBySource($questions, $links, $materials)
);
if (($urlAudit['status'] ?? '') !== 'CURRENT_PARTIAL') $fail('Persisted URL audit should be valid and explicitly partial.');
$urlStatus = [];
foreach (($urlSnapshots[0]['records'] ?? []) as $record) $urlStatus[(string) ($record['source_id'] ?? '')] = (string) ($record['status'] ?? '');
$replacementExpected = [
    'philippines-negotiable-instruments-law' => 'https://lawphil.net/statutes/acts/act_2031_1911.html',
    'ra-9173-philippine-nursing-act' => 'https://lawphil.net/statutes/repacts/2002/ra_9173_2002.html',
    'republic-act-7836' => 'https://www.officialgazette.gov.ph/1994/12/16/republic-act-no-7836/',
    'prc-lept-alignment-guidance-2025' => 'https://www.prc.gov.ph/article/guidelines-implementation-prc-ched-jmc-alignment-teacher-education-curriculum',
    'ifrs-ias-21-foreign-exchange' => 'https://www.ifrs.org/issued-standards/list-of-standards/ias-21-effects-of-changes-in-foreign-exchange-rates/',
    'iaasb-isa-240-fraud' => 'https://www.iaasb.org/publications/isa-240-revised-auditors-responsibilities-relating-fraud-audit-financial-statements',
    'iaasb-isa-315-risk-assessment' => 'https://www.iaasb.org/publications/isa-315-revised-2019',
    'iaasb-isa-700-audit-opinion' => 'https://www.iaasb.org/publications/isa-700-revised-forming-opinion-and-reporting-financial-statements',
    'iaasb-isa-230-documentation' => 'https://www.iaasb.org/publications/isa-230-audit-documentation',
    'iaasb-isa-265-control-deficiencies' => 'https://www.iaasb.org/publications/isa-265-communicating-deficiencies-internal-control-those-charged-governance-and-management',
    'iaasb-isa-450-misstatements' => 'https://www.iaasb.org/publications/isa-450-evaluation-misstatements-identified-during-audit',
    'iaasb-isa-500-audit-evidence' => 'https://www.iaasb.org/publications/isa-500-audit-evidence',
    'iaasb-isa-520-analytical-procedures' => 'https://www.iaasb.org/publications/isa-520-analytical-procedures',
    'iaasb-isa-530-audit-sampling' => 'https://www.iaasb.org/publications/isa-530-audit-sampling',
];
foreach ($replacementExpected as $id => $oldUrl) {
    if (($urlStatus[$id] ?? '') !== 'HTTP_REACHABLE') $fail('Replacement URL was not freshly checked as reachable: ' . $id);
    $history = (array) ($sourceById[$id]['url_change_history'] ?? []);
    $last = null;
    foreach (array_reverse($history) as $entry) if (($entry['from_url'] ?? '') === $oldUrl) { $last = $entry; break; }
    if (!is_array($last)
        || ($last['to_url'] ?? '') !== ($sourceById[$id]['url'] ?? '')
        || ($last['original_status'] ?? '') !== 'CONFIRMED_404'
        || (int) ($last['replacement_http_status'] ?? 0) !== 200
        || ($last['replacement_final_url'] ?? '') !== ($sourceById[$id]['url'] ?? '')
        || ($last['human_review_status'] ?? '') !== 'REVIEW_REQUIRED') {
        $fail('Source URL replacement provenance or review hold is incomplete: ' . $id);
    }
}
$handbookIds = ['iaasb-isa-230-documentation', 'iaasb-isa-265-control-deficiencies', 'iaasb-isa-450-misstatements', 'iaasb-isa-500-audit-evidence', 'iaasb-isa-520-analytical-procedures', 'iaasb-isa-530-audit-sampling'];
foreach ($handbookIds as $id) {
    if (($sourceById[$id]['access_status'] ?? '') !== 'METADATA_ONLY_FULL_TEXT_PURCHASE_REQUIRED'
        || ($sourceById[$id]['source_text_verified'] ?? true) !== false
        || ($sourceById[$id]['human_review_status'] ?? '') !== 'REVIEW_REQUIRED') {
        $fail('IAASB handbook metadata-only access limitation was not preserved for ' . $id . '.');
    }
}
$remaining404 = array_keys(array_filter($urlStatus, static fn(string $status): bool => $status === 'CONFIRMED_404'));
sort($remaining404);
if ($remaining404 !== ['aasc-psa-500-currentness-review', 'deped-matatag-general-education-framework', 'prc-lept-program-march-2026']) $fail('Remaining confirmed-404 records must be the three unreferenced items, not referenced source records.');
$liveAffectedBySource = SourceUrlHealthSnapshot::affectedQuestionsBySource($questions, $links, $materials);
foreach ($remaining404 as $sourceId) if (($liveAffectedBySource[$sourceId] ?? []) !== []) $fail('A remaining confirmed-404 record still has live question or study relationships: ' . $sourceId);
if (count(array_filter($urlStatus, static fn(string $status): bool => $status === 'ACCESS_RESTRICTED')) < 1) $fail('Restricted references must remain visible as nonfatal warnings.');

$legacyIds = ['5206', '5462', '5463', '5464', '5465', '5466', '5467', '5468', '5487', '5488', '5489', '5490', '5491', '5492', '6602'];
foreach ($legacyIds as $id) {
    $question = $questionById[$id] ?? null;
    if (!is_array($question) || !isset($materialById[(string) ($question['study_material_id'] ?? '')])) $fail('Legacy material pointer is unresolved for Q' . $id . '.');
    $currentLink = null;
    foreach ($links as $candidate) if ((string) ($candidate['question_id'] ?? '') === $id) { $currentLink = $candidate; break; }
    if (!is_array($currentLink) || !isset($materialById[(string) ($currentLink['material_id'] ?? '')])) $fail('Current study relationship is unresolved for Q' . $id . '.');
}
$standalone = $materialById['let-filipino-pagbasa-wika-at-pagsulat'] ?? null;
if (!is_array($standalone) || ($standalone['status'] ?? '') !== 'published' || !in_array('let', (array) ($standalone['exams'] ?? []), true)) $fail('LET Filipino published standalone material is no longer discoverable by board.');
foreach ((array) ($standalone['source_ids'] ?? []) as $sourceId) if (!isset($sourceById[(string) $sourceId])) $fail('LET Filipino standalone material has a broken source chain.');

foreach (['1536', '1537'] as $id) {
    $found = false;
    foreach ($links as $link) if ((string) ($link['question_id'] ?? '') === $id && trim((string) ($link['section_id'] ?? $link['lesson_section_id'] ?? '')) !== '') $found = true;
    if ($found) $fail('Q' . $id . ' exact-section quarantine was not preserved.');
}
foreach (['1925', '1926', '1927'] as $id) {
    $questionLink = null;
    foreach ($links as $candidate) if ((string) ($candidate['question_id'] ?? '') === $id) { $questionLink = $candidate; break; }
    if (!is_array($questionLink) || !in_array('philippines-ease-of-paying-taxes-act', (array) ($questionLink['source_ids'] ?? []), true)) $fail('Existing BIR RR 7-2024 study source relationship changed for Q' . $id . '.');
}

// Exercise the existing RepositoryHealthEngine with dead, restricted, valid,
// invalid-ID, material-only, exact-section, and explicitly rejected fixtures.
$fixtureSources = [
    ['id' => 'valid-source', 'title' => 'Valid source', 'authority' => 'Publisher', 'url' => 'https://valid.example/source', 'access_status' => 'METADATA_ONLY_FULL_TEXT_PURCHASE_REQUIRED', 'source_text_verified' => false, 'human_review_status' => 'REVIEW_REQUIRED'],
    ['id' => 'dead-source', 'title' => 'Dead source', 'authority' => 'Publisher', 'url' => 'https://dead.example/source'],
    ['id' => 'restricted-source', 'title' => 'Restricted source', 'authority' => 'Publisher', 'url' => 'https://restricted.example/source'],
    ['id' => 'unreferenced-dead-source', 'title' => 'Unreferenced dead source', 'authority' => 'Publisher', 'url' => 'https://orphan-dead.example/source'],
];
if (count($urlSnapshots[0]['records'] ?? []) > 100 || strlen((string) file_get_contents($root . '/storage/source-url-health.json')) > 20000) $fail('Persisted URL evidence is not compactly stored.');
foreach (($urlSnapshots[0]['records'] ?? []) as $record) if (array_key_exists('affected_question_ids', $record)) $fail('Persisted URL evidence duplicates question IDs instead of deriving dashboard drill-downs live.');
$fixtureRecords = [
    ['source_id' => 'valid-source', 'url' => 'https://valid.example/source', 'status' => 'HTTP_REACHABLE', 'checked_at_utc' => '2026-10-23T10:00:00Z', 'http_status' => 200, 'final_url' => 'https://valid.example/source', 'evidence_ref' => 'fixture'],
    ['source_id' => 'dead-source', 'url' => 'https://dead.example/source', 'status' => 'CONFIRMED_404', 'checked_at_utc' => '2026-10-23T10:00:00Z', 'http_status' => 404, 'final_url' => 'https://dead.example/source', 'evidence_ref' => 'fixture'],
    ['source_id' => 'restricted-source', 'url' => 'https://restricted.example/source', 'status' => 'ACCESS_RESTRICTED', 'checked_at_utc' => '2026-10-23T10:00:00Z', 'http_status' => 403, 'final_url' => 'https://restricted.example/source', 'evidence_ref' => 'fixture'],
    ['source_id' => 'unreferenced-dead-source', 'url' => 'https://orphan-dead.example/source', 'status' => 'CONFIRMED_404', 'checked_at_utc' => '2026-10-23T10:00:00Z', 'http_status' => 404, 'final_url' => 'https://orphan-dead.example/source', 'evidence_ref' => 'fixture'],
];
$fixtureSnapshot = [
    'id' => 'latest',
    'schema_version' => SourceUrlHealthSnapshot::SCHEMA_VERSION,
    'generated_at_utc' => '2026-10-23T10:00:00Z',
    'registry_fingerprint' => SourceUrlHealthSnapshot::registryFingerprint($fixtureSources),
    'coverage' => ['complete' => true, 'checked_source_count' => 4, 'total_url_source_count' => 4, 'scope' => 'isolated test fixture'],
    'records' => $fixtureRecords,
];
$fixtureSnapshot['payload_sha256'] = SourceUrlHealthSnapshot::payloadDigest($fixtureSnapshot);
$fixtureQuestions = [];
foreach ([['1', 'missing-source'], ['2', 'dead-source'], ['3', 'restricted-source'], ['4', 'valid-source']] as [$id, $sourceId]) {
    $fixtureQuestions[] = ['id' => $id, 'board' => 'let', 'subject' => 'fixture-subject', 'question' => 'Fixture question ' . $id, 'choices' => ['A', 'B'], 'answer' => 'A', 'explanation' => 'Fixture rationale.', 'source_ids' => [$sourceId]];
}
$fixtureCollections = [
    'questions' => $fixtureQuestions,
    'sources' => $fixtureSources,
    'source-url-health' => [$fixtureSnapshot],
    'study-materials' => [
        ['id' => 'material-dead', 'title' => 'Dead source material', 'source_ids' => ['dead-source'], 'exams' => ['let']],
        ['id' => 'material-exact', 'title' => 'Exact support material', 'source_ids' => ['valid-source'], 'exams' => ['let']],
    ],
    'study-lessons' => [['id' => 'lesson-exact', 'material_id' => 'material-exact', 'status' => 'published', 'sections' => [['id' => 'section-exact']]]],
    'study-question-links' => [
        ['id' => 'material-only', 'question_id' => '2', 'material_id' => 'material-dead', 'source_ids' => ['dead-source']],
        ['id' => 'rejected-exact', 'question_id' => '3', 'material_id' => 'material-dead', 'section_id' => 'rejected-section', 'source_ids' => ['restricted-source'], 'semantic_review_status' => 'REJECTED'],
        ['id' => 'valid-exact', 'question_id' => '4', 'material_id' => 'material-exact', 'lesson_id' => 'lesson-exact', 'section_id' => 'section-exact', 'source_ids' => ['valid-source']],
    ],
    'boards' => [], 'subjects' => [], 'blueprints' => [], 'question-eligibility' => [],
    'concept-derived-eligibility' => [], 'question-reuse-overrides' => [], 'question-reuse-metadata' => [],
    'board-concept-coverage' => [], 'content-fit-audits' => [], 'flashcard-exclusions' => [],
];
foreach (['taxonomy/domains', 'taxonomy/topics', 'taxonomy/concepts'] as $collection) $fixtureCollections[$collection] = [];
$fixtureSnapshot['payload_sha256'] = SourceUrlHealthSnapshot::payloadDigest($fixtureSnapshot);
$fixtureCollections['source-url-health'] = [$fixtureSnapshot];
$fixtureReport = RepositoryHealthEngine::analyzeCollections($fixtureCollections);
$fixtureCodes = [];
foreach ($fixtureReport->issues as $issue) $fixtureCodes[$issue->code] = ($fixtureCodes[$issue->code] ?? 0) + 1;
foreach (['INVALID_QUESTION_SOURCE_REFERENCE', 'SOURCE_URL_ACCESS_RESTRICTED', 'SOURCE_TEXT_ACCESS_LIMITED'] as $code) if (($fixtureCodes[$code] ?? 0) !== 1) $fail('Developer health engine did not classify fixture issue exactly once: ' . $code);
if (($fixtureCodes['SOURCE_URL_CONFIRMED_404'] ?? 0) !== 2) $fail('Dashboard should retain both referenced and unreferenced confirmed-404 evidence.');
$deadStatuses = [];
foreach ($fixtureReport->issues as $issue) if (($issue->code ?? '') === 'SOURCE_URL_CONFIRMED_404') $deadStatuses[(string) ($issue->entityId ?? '')] = (string) ($issue->status ?? '');
if (($deadStatuses['dead-source'] ?? '') !== 'FAIL' || ($deadStatuses['unreferenced-dead-source'] ?? '') !== 'REVIEW') $fail('A dead required source and an unreferenced dead catalog record were assigned the same severity.');
if (isset($fixtureCodes['SOURCE_URL_AUDIT_UNAVAILABLE']) || isset($fixtureCodes['MISSING_EXACT_SECTION_LINK'])) $fail('Optional/missing exact link or valid snapshot was misclassified as a broken source.');
$relationships = $fixtureReport->summary['study_relationships'] ?? [];
if (($relationships['canonical_questions_with_source_only_references'] ?? 0) !== 2
    || ($relationships['material_level_link_rows'] ?? 0) !== 1
    || ($relationships['exact_section_link_rows'] ?? 0) !== 1
    || ($relationships['explicitly_rejected_mapping_rows'] ?? 0) !== 1
    || ($relationships['exact_links_approved_with_explicit_evidence'] ?? 0) !== 0) {
    $fail('Dashboard relationship summary conflated source-only, material, exact, rejected, or academic states.');
}
$corrupt = [$fixtureSnapshot];
$corrupt[0]['payload_sha256'] = str_repeat('0', 64);
if ((SourceUrlHealthSnapshot::inspect($corrupt, $fixtureSources, new DateTimeImmutable('2026-10-23T11:00:00Z'))['status'] ?? '') !== 'CORRUPT') $fail('Corrupt URL snapshot did not fail safely.');
if ((SourceUrlHealthSnapshot::inspect([], $fixtureSources, new DateTimeImmutable('2026-10-23T11:00:00Z'))['status'] ?? '') !== 'UNAVAILABLE') $fail('Missing URL snapshot did not report unavailable evidence.');
$staleSources = $fixtureSources;
$staleSources[0]['url'] = 'https://valid.example/changed';
if ((SourceUrlHealthSnapshot::inspect([$fixtureSnapshot], $staleSources, new DateTimeImmutable('2026-10-23T11:00:00Z'))['status'] ?? '') !== 'STALE') $fail('Source registry change did not stale its URL snapshot.');
$changedFixtureQuestions = $fixtureQuestions;
$changedFixtureQuestions[1]['source_ids'] = ['restricted-source'];
$changedFixtureLinks = $fixtureCollections['study-question-links'];
$changedFixtureLinks[0]['source_ids'] = ['restricted-source'];
$changedFixtureMaterials = $fixtureCollections['study-materials'];
$changedFixtureMaterials[0]['source_ids'] = ['restricted-source'];
$changedAffected = SourceUrlHealthSnapshot::affectedQuestionsBySource($changedFixtureQuestions, $changedFixtureLinks, $changedFixtureMaterials);
$changedAudit = SourceUrlHealthSnapshot::inspect([$fixtureSnapshot], $fixtureSources, new DateTimeImmutable('2026-10-23T11:00:00Z'), $changedAffected);
$deadFinding = array_values(array_filter($changedAudit['findings'], static fn(array $finding): bool => ($finding['code'] ?? '') === 'SOURCE_URL_CONFIRMED_404'))[0] ?? null;
if (($changedAudit['status'] ?? '') !== 'CURRENT_COMPLETE' || !is_array($deadFinding) || in_array('2', (array) ($deadFinding['evidence']['affected_question_ids'] ?? []), true)) $fail('Dashboard source drill-down did not reflect changed question references without duplicating question IDs in the URL snapshot.');

echo 'BATCH707_REMEDIATION_PASS=1' . PHP_EOL;
echo 'Q6403_CURRENT_KEY_HELD=1' . PHP_EOL;
echo 'Q6403_NEW_PRACTICE_EXCLUDED=1' . PHP_EOL;
echo 'DIRECT_INVALID_SOURCE_IDS=' . count($invalidDirect) . PHP_EOL;
echo 'URL_REPLACEMENTS_VERIFIED=' . count($replacementExpected) . PHP_EOL;
echo 'Q6403_BOARD_EXCLUSION_SCOPE=CIVIL_SERVICE_ONLY' . PHP_EOL;
echo 'REMAINING_REFERENCED_404S=0' . PHP_EOL;
echo 'URL_SNAPSHOT_STATUS=' . $urlAudit['status'] . PHP_EOL;
echo 'URL_CHECKED=' . (int) ($urlAudit['summary']['checked_source_count'] ?? 0) . '/' . (int) ($urlAudit['summary']['total_url_source_count'] ?? 0) . PHP_EOL;
echo 'LEGACY_POINTERS_RECONCILED=' . count($legacyIds) . PHP_EOL;
echo 'STUDY_DASHBOARD_FIXTURE=PASS' . PHP_EOL;
echo 'URL_EVIDENCE_COMPACT=PASS' . PHP_EOL;
