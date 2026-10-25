<?php

declare(strict_types=1);

require_once __DIR__ . '/Support/StaticStorageFixture.php';

$root = dirname(__DIR__, 2);
$fixture = StaticStorageFixture::create($root, 'batch716-evidence-handoff');
$cleanup = true;
register_shutdown_function(static function () use ($fixture, &$cleanup): void {
    if (!$cleanup) return;
    $real = realpath($fixture);
    $temporary = realpath(sys_get_temp_dir());
    if (!is_string($real) || !is_string($temporary)
        || !str_starts_with($real, $temporary . DIRECTORY_SEPARATOR)
        || !str_starts_with(basename($real), 'boardprep-batch716-evidence-handoff-')) return;
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($real, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($iterator as $entry) {
        if ($entry->isDir() && !$entry->isLink()) @rmdir($entry->getPathname());
        else @unlink($entry->getPathname());
    }
    @rmdir($real);
});

foreach ([
    'APP_ENV' => 'testing', 'APP_DEBUG' => 'false', 'APP_TIMEZONE' => 'UTC',
    'DB_DRIVER' => 'json', 'APP_STORAGE_PATH' => $fixture,
    'APP_SESSION_PATH' => $fixture . '/sessions', 'BOARDPREP_ACCOUNTS_ENABLED' => 'true',
] as $key => $value) {
    putenv($key . '=' . $value);
    $_ENV[$key] = $value;
}
@mkdir($fixture . '/sessions', 0700, true);
require_once $root . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();
\App\Core\App::boot();

$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException('Batch 716 evidence handoff failed: ' . $message);
};
$readJson = static function (string $path) use ($root): array {
    $raw = file_get_contents($root . '/' . $path);
    if (!is_string($raw)) throw new RuntimeException('Unable to read ' . $path);
    $value = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($value)) throw new RuntimeException('Invalid JSON object in ' . $path);
    return $value;
};

$protected = [
    'questions.json' => hash_file('sha256', $root . '/storage/questions.json'),
    'question-eligibility.json' => hash_file('sha256', $root . '/storage/question-eligibility.json'),
    'sources.json' => hash_file('sha256', $root . '/storage/sources.json'),
    'study-question-links.json' => hash_file('sha256', $root . '/storage/study-question-links.json'),
    'question-statistics.json' => is_file($root . '/storage/question-statistics.json') ? hash_file('sha256', $root . '/storage/question-statistics.json') : null,
];

$cohort = $readJson('docs/reports/batch-715-pilot-cohort.json');
$readiness = $readJson('docs/reports/batch-715-review-readiness.json');
$matrix = $readJson('docs/reports/batch-716-source-evidence-matrix.json');
$packets = $readJson('docs/reports/batch-716-pilot-review-packets.json');
$governance = $readJson('docs/reports/batch-716-taxonomy-study-governance.json');
$expectedIds = ['9124','9125','9126','9127','9128','9140','9164','9200','9204','9210','9228','9238','9250','9280','9290','9302','9320','9348','9360','9378'];
$cohortIds = array_values(array_map(static fn(array $row): string => (string) ($row['question_id'] ?? ''), array_values(array_filter((array) ($cohort['items'] ?? []), 'is_array'))));
$assert($cohortIds === $expectedIds, 'The B715 pilot question IDs changed or were reordered.');
$assert(($matrix['schema'] ?? '') === 'boardprep.batch-716-source-evidence-matrix.v1', 'Source matrix schema is invalid.');
$assert(($packets['schema'] ?? '') === 'boardprep.batch-716-pilot-review-packets.v1', 'Review packet schema is invalid.');
$assert(count($packets['items'] ?? []) === 20 && (int) ($packets['packet_count'] ?? 0) === 20, 'Expected one packet per pilot proposal.');
$assert((int) ($packets['real_approvals'] ?? -1) === 0 && (int) ($packets['real_imports'] ?? -1) === 0, 'Packet package contains a real approval or import.');
$packetIds = array_values(array_map(static fn(array $packet): string => (string) ($packet['question_id'] ?? ''), array_values(array_filter((array) ($packets['items'] ?? []), 'is_array'))));
$assert($packetIds === $expectedIds, 'Review packets do not cover each selected proposal exactly once in manifest order.');
foreach ((array) ($packets['items'] ?? []) as $packet) {
    $assert(($packet['review_state'] ?? '') === 'PENDING_REVIEW'
        && ($packet['academic_approval'] ?? '') === 'NOT_APPROVED'
        && ($packet['production_status'] ?? '') === 'NONCANONICAL_NOT_IN_LEARNER_POOLS', 'A packet claims a real decision or learner publication.');
    $assert(!empty($packet['question']['stem']) && !empty($packet['question']['choices'])
        && !empty($packet['question']['proposed_answer']) && !empty($packet['question']['explanation']), 'A packet omitted reviewed question content.');
    $assert(array_key_exists('taxonomy_candidates', $packet) && array_key_exists('study_link_candidates', $packet)
        && array_key_exists('selection_rationale', $packet) && array_key_exists('review_questions', $packet), 'A packet omitted taxonomy, study, selection, or reviewer-question context.');
}
$governanceSummary = (array) ($governance['summary'] ?? []);
$assert(($governance['schema'] ?? '') === 'boardprep.batch-716-taxonomy-study-governance.v1'
    && count($governance['items'] ?? []) === 20, 'Taxonomy/study governance worksheet is missing or incomplete.');
$assert((int) ($governanceSummary['topic_items_unregistered'] ?? -1) === 19
    && (int) ($governanceSummary['topic_items_with_exact_registered_record'] ?? -1) === 1
    && (int) ($governanceSummary['recorded_topic_candidate_edges'] ?? -1) === 4
    && (int) ($governanceSummary['concept_items_unregistered'] ?? -1) === 20,
    'Governance worksheet did not reconcile current proposed taxonomy states and candidate edges.');
$assert((int) ($governanceSummary['exact_study_section_candidates'] ?? -1) === 3
    && (int) (($governanceSummary['study_relationships']['MISSING_SECTION'] ?? -1)) === 17
    && (int) ($governanceSummary['taxonomy_activations'] ?? -1) === 0
    && (int) ($governanceSummary['study_link_activations'] ?? -1) === 0,
    'Governance worksheet misreported study candidates or claims an unauthorized activation.');
foreach ((array) ($governance['provenance'] ?? []) as $source) {
    $path = (string) ($source['path'] ?? '');
    $assert($path !== '' && is_file($root . '/' . $path)
        && hash_equals(hash_file('sha256', $root . '/' . $path), (string) ($source['sha256'] ?? '')),
        'Governance worksheet snapshot is stale for ' . $path);
}
$rows = array_values(array_filter((array) ($matrix['citation_rows'] ?? []), 'is_array'));
$assert(count($rows) === 41, 'Expected all 41 citation rows.');
$summary = (array) ($matrix['summary'] ?? []);
$assert((int) ($summary['pilot_proposals'] ?? 0) === 20 && (int) ($summary['unique_sources'] ?? 0) === 12, 'Pilot or unique-source count differs from the manifest.');
$assert((int) ($summary['content_examined_citation_rows'] ?? 0) === 40
    && (int) ($summary['content_not_examined_citation_rows'] ?? 0) === 1, 'The scanned DO 40 citation must remain unexamined while the other source content is accounted for.');
$assert((int) ($summary['qualified_source_reviews_complete'] ?? -1) === 0
    && (int) ($summary['qualified_source_reviews_pending'] ?? -1) === 41
    && (int) ($summary['machine_claims_verified'] ?? -1) === 0
    && (int) ($summary['academic_approvals'] ?? -1) === 0, 'Machine evidence was promoted to a human decision.');
$assert((int) ($summary['missing_source_registry_records'] ?? -1) === 0
    && (int) ($summary['stale_source_registry_snapshots'] ?? -1) === 0
    && (int) ($summary['duplicate_source_registry_ids'] ?? -1) === 0
    && (int) ($summary['duplicate_citation_keys'] ?? -1) === 0, 'Matrix reports a registry or citation reconciliation defect.');

$registry = $readJson('storage/sources.json');
$registryById = [];
foreach ($registry as $record) if (is_array($record)) $registryById[(string) ($record['id'] ?? '')] = $record;
$proposalWorkflow = new \App\Services\Question\AcademicReviewWorkflowService(
    \App\Core\App::storage(),
    \App\Services\Question\AcademicReviewProposalService::all(),
    new \App\Services\Question\AcademicReviewerAuthorizationService(\App\Core\App::storage()),
    $fixture
);
$proposalsByQuestion = [];
foreach (\App\Services\Question\AcademicReviewProposalService::all() as $proposal) {
    $proposalsByQuestion[(string) ($proposal['question_id'] ?? '')] = $proposal;
}
$keys = [];
$sources = [];
$examined = 0;
$unexamined = 0;
$reachable = 0;
$identityConfirmed = 0;
foreach ($rows as $row) {
    $qid = (string) ($row['question_id'] ?? '');
    $sid = (string) ($row['source_identity']['source_id'] ?? '');
    $key = $qid . '::' . $sid;
    $assert(!isset($keys[$key]), 'Duplicate citation key: ' . $key);
    $keys[$key] = true;
    $sources[$sid] = true;
    $currentVersion = $proposalWorkflow->version((string) ($row['proposal_id'] ?? ''));
    $assert(hash_equals($currentVersion, (string) ($row['question_version'] ?? '')), 'Evidence is version-stale for question ' . $qid);
    $sourceRecord = $registryById[$sid] ?? null;
    $assert(is_array($sourceRecord), 'Source registry identity missing for ' . $key);
    $canonical = static function (mixed $value) use (&$canonical): mixed {
        if (!is_array($value)) return $value;
        if (array_is_list($value)) return array_map($canonical, $value);
        ksort($value, SORT_STRING);
        foreach ($value as $name => $child) $value[$name] = $canonical($child);
        return $value;
    };
    $sourceHash = static fn(array $record): string => hash('sha256', json_encode($canonical($record), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    $assert(hash_equals($sourceHash($sourceRecord), (string) ($row['source_registry']['current_record_sha256'] ?? '')), 'Matrix source hash is stale for ' . $key);
    $assert(!empty($row['source_registry']['snapshot_matches_current_record']), 'B695 snapshot differs from current source record for ' . $key);
    $assert(($row['human_source_review'] ?? '') === 'PENDING_QUALIFIED_REVIEWER'
        && ($row['academic_approval'] ?? '') === 'NOT_APPROVED', 'A human decision was fabricated for ' . $key);
    $retrieval = (array) ($row['retrieval'] ?? []);
    $assert(in_array(($retrieval['retrieval_time_status'] ?? ''), ['RECORDED', 'EXACT_TIME_NOT_RETAINED'], true)
        && (($retrieval['retrieved_at_utc'] ?? null) !== null || !empty($retrieval['retrieval_time_note'])),
        'Missing or inferred retrieval-time provenance for ' . $key);
    if (!empty($row['retrieval']['url_reachable'])) $reachable++;
    if (!empty($row['retrieval']['identity_confirmed'])) $identityConfirmed++;
    if (!empty($row['content_examined'])) $examined++; else $unexamined++;
}
$assert(count($sources) === 12 && $examined === 40 && $unexamined === 1, 'Derived source matrix totals differ from the reported totals.');
$assert($reachable === (int) ($summary['url_reachable_citation_rows'] ?? -1)
    && $identityConfirmed === (int) ($summary['identity_confirmed_citation_rows'] ?? -1), 'Transport, identity, and content-examined counts were collapsed or misreported.');

$annexRows = array_values(array_filter($rows, static fn(array $row): bool => ($row['source_identity']['source_id'] ?? '') === 'prc-resolution-20-2025-annex-a'));
$newlyExamined = array_values(array_filter($annexRows, static fn(array $row): bool => ($row['content_examined_provenance'] ?? '') === 'B716_DIRECT_PAGE_RANGE_INSPECTION'));
$assert(count($newlyExamined) === 16, 'Expected direct page inspection for every previously unexamined Annex citation row.');
foreach ($newlyExamined as $row) {
    $context = (array) ($row['additional_official_context']['bounded_range_inspection'] ?? []);
    $assert(in_array((int) ($row['question_id'] ?? 0), [9348,9360,9378], true)
        || in_array((int) ($row['question_id'] ?? 0), [9128,9140,9164,9200,9204,9210,9228,9238,9250,9280,9290,9302,9320], true),
        'Unexpected question ID was marked as a direct Annex inspection.');
    $assert(in_array((int) ($row['locator']['page_or_section'][0] ?? 0), range(1,12), true)
        || ($row['locator']['page_or_section'] ?? []) === [65,66],
        'Annex inspection did not record the exact inspected page range.');
    $assert((int) ($context['aggregate_unique_range_bytes_reported'] ?? 0) <= 18000000
        && empty($context['whole_binary_saved'])
        && empty($context['per_interval_log_retained']),
        'Bounded Annex reading must remain partial, page-specific and explicitly limited.');
    $assert(!empty($row['blocking_reasons']) && ($row['human_source_review'] ?? '') === 'PENDING_QUALIFIED_REVIEWER', 'Annex evidence incorrectly cleared human source review.');
}
$assert((int) ($summary['annex_rows_newly_content_examined'] ?? 0) === 16
    && (int) ($summary['annex_rows_still_content_unexamined'] ?? -1) === 0, 'Previously unexamined Annex citation rows were not reconciled.');
$scannedDeped = array_values(array_filter($rows, static fn(array $row): bool => ($row['question_id'] ?? '') === '9228' && ($row['source_identity']['source_id'] ?? '') === 'deped-do-40-2012-child-protection'));
$assert(count($scannedDeped) === 1 && empty($scannedDeped[0]['content_examined'])
    && in_array('CITATION_CONTENT_NOT_EXAMINED', (array) ($scannedDeped[0]['blocking_reasons'] ?? []))
    && ($scannedDeped[0]['machine_claim_check']['status'] ?? '') === 'CONTENT_NOT_EXAMINED', 'Scanned inaccessible procedural evidence was incorrectly treated as examined.');

$readinessById = [];
foreach ((array) ($readiness['items'] ?? []) as $item) $readinessById[(string) ($item['question_id'] ?? '')] = $item;
foreach ($expectedIds as $qid) {
    $proposal = $proposalsByQuestion[$qid] ?? null;
    $assert(is_array($proposal), 'Missing recovered proposal ' . $qid);
    $assert(\App\Core\App::storage()->find('questions', $qid) === null, 'Noncanonical proposal entered canonical storage: ' . $qid);
    $workflowRecord = $proposalWorkflow->record((string) $proposal['id']);
    $assert(($workflowRecord['state'] ?? 'PENDING_REVIEW') === 'PENDING_REVIEW'
        && empty($workflowRecord['decisions']), 'Real pilot item has a fabricated workflow decision: ' . $qid);
    $assert(($readinessById[$qid]['question_version'] ?? '') === $proposalWorkflow->version((string) $proposal['id']), 'B715 dossier is stale for ' . $qid);
}
$assert(\App\Core\App::storage()->all('academic-reviewer-authorizations') === [], 'Real reviewer grant appeared in isolated fixture.');
$assert(\App\Core\App::storage()->all('academic-import-journal') === [], 'A canonical import journal appeared in isolated fixture.');

// Recheck section matches through the existing production StudyLibraryService.
foreach ((array) ($governance['items'] ?? []) as $governanceItem) {
    $relationship = (array) ($governanceItem['study_relationship'] ?? []);
    $materialId = (string) ($relationship['proposed_material_id'] ?? '');
    $sectionId = (string) ($relationship['proposed_section_id'] ?? '');
    $material = $materialId === '' ? null : \App\Services\Study\StudyLibraryService::find($materialId);
    $sectionExists = false;
    foreach ((array) (($material['lesson']['sections'] ?? [])) as $section) {
        if (is_array($section) && (string) ($section['id'] ?? '') === $sectionId) $sectionExists = true;
    }
    $assert(($relationship['current_registry_match'] ?? '') === ($sectionExists ? 'EXACT_SECTION_MATCH' : 'MISSING_SECTION'),
        'Governance worksheet study candidate disagrees with production StudyLibraryService for Q' . (string) ($governanceItem['question_id'] ?? ''));
}

// Exercise the real private inspector route with one explicit synthetic grant.
require_once dirname(__DIR__) . '/Doctor/Project/BoardPrep/Simulation/HttpSimulator.php';
$accountId = 'account-' . bin2hex(random_bytes(16));
$learnerId = 'account-learner-' . bin2hex(random_bytes(16));
$accounts = new \App\Services\Accounts\AccountRepository($fixture . '/private/learner-accounts');
$assert($accounts->create([
    'account_id' => $accountId, 'learner_id' => $learnerId,
    'username' => 'b716-fixture-' . bin2hex(random_bytes(5)),
    'password_hash' => password_hash('fixture-only-password', PASSWORD_DEFAULT),
    'status' => 'active', 'auth_version' => 1,
    'created_at' => gmdate(DATE_ATOM), 'updated_at' => gmdate(DATE_ATOM),
]), 'Unable to create synthetic test account.');
\App\Core\App::storage()->create('academic-reviewer-authorizations', [
    'id' => 'b716-test-only-grant-' . bin2hex(random_bytes(4)),
    'account_id' => $accountId, 'authorization_status' => 'ACTIVE',
    'qualification_type' => 'SUBJECT_EXPERT', 'qualification_reference' => 'SYNTHETIC_TEST_ONLY',
    'authorized_by' => 'SYNTHETIC_TEST_ONLY_OWNER', 'authorized_at' => gmdate(DATE_ATOM),
    'expires_at' => '2099-12-31T23:59:59+00:00',
    'scopes' => [[
        'board_id' => 'let', 'subject_id' => 'technical-vocational-teacher-education',
        'capabilities' => ['REVIEW', 'SOURCE_REVIEW'],
    ]],
]);
$http = new \Tools\Doctor\Project\BoardPrep\Simulation\HttpSimulator($root . '/public/index.php');
$sessionId = 'b716fixture' . bin2hex(random_bytes(8));
ini_set('session.use_cookies', '0');
ini_set('session.use_strict_mode', '0');
session_save_path($http->sessionPath());
session_id($sessionId);
session_start();
$_SESSION = [
    'boardprep_account_auth' => [
        'account_id' => $accountId, 'learner_id' => $learnerId, 'auth_version' => 1,
        'started_at' => time(), 'last_activity' => time(),
    ],
    'learner_identity' => $learnerId,
    '_csrf_token' => bin2hex(random_bytes(32)),
];
session_write_close();
$beforeReads = [
    'workflow' => \App\Core\App::storage()->all('academic-proposal-workflow'),
    'questions' => \App\Core\App::storage()->all('questions'),
    'links' => \App\Core\App::storage()->all('study-question-links'),
];
$response = $http->request('GET', '/developer/academic-review/proposal', ['id' => 'batch695-9348'], [], [], ['PHPSESSID' => $sessionId]);
$assert(($response['status'] ?? 0) === 200, 'Scoped synthetic reviewer cannot access the private item inspector: HTTP '
    . (string) ($response['status'] ?? 'missing') . ' stderr=' . trim((string) ($response['stderr'] ?? ''))
    . ' body=' . substr(strip_tags((string) ($response['output'] ?? '')), 0, 240));
$html = (string) $response['output'];
foreach ([
    'Proposal #9348', 'Batch 716 source recheck', 'not expert review', 'pp. 65–66',
    'PENDING_QUALIFIED_REVIEWER', 'Print review packet', 'Next eligible action:',
    'No academic decision or source review has been recorded',
] as $phrase) {
    $assert(str_contains($html, $phrase), 'Private reviewer inspector omitted expected evidence or state: ' . $phrase);
}
$assert(!str_contains($html, 'Import this approved proposal'), 'Real proposal exposed an import confirmation before decisions.');
$assert(\App\Core\App::storage()->all('academic-proposal-workflow') === $beforeReads['workflow']
    && \App\Core\App::storage()->all('questions') === $beforeReads['questions']
    && \App\Core\App::storage()->all('study-question-links') === $beforeReads['links'], 'Read-only inspector mutated review or canonical records.');
$unauthenticated = $http->request('GET', '/developer/academic-review/proposal', ['id' => 'batch695-9348']);
$assert(($unauthenticated['status'] ?? 0) === 401, 'Anonymous reviewer was allowed into private packet.');
$foreign = $http->request('GET', '/developer/academic-review/proposal', ['id' => 'batch695-9290'], [], [], ['PHPSESSID' => $sessionId]);
$assert(($foreign['status'] ?? 0) === 403, 'Reviewer inspected a proposal outside their subject scope.');

$assert(hash_file('sha256', $root . '/storage/questions.json') === $protected['questions.json'], 'Canonical question file changed.');
$canonicalQuestions = json_decode((string) file_get_contents($root . '/storage/questions.json'), true, 512, JSON_THROW_ON_ERROR);
$assert(is_array($canonicalQuestions) && count($canonicalQuestions) === 9123, 'Canonical question count changed.');
$assert(hash_file('sha256', $root . '/storage/question-eligibility.json') === $protected['question-eligibility.json'], 'Eligibility file changed.');
$assert(hash_file('sha256', $root . '/storage/sources.json') === $protected['sources.json'], 'Source registry changed.');
$assert(hash_file('sha256', $root . '/storage/study-question-links.json') === $protected['study-question-links.json'], 'Study-link file changed.');
$assert(($protected['question-statistics.json'] === null && !is_file($root . '/storage/question-statistics.json'))
    || hash_file('sha256', $root . '/storage/question-statistics.json') === $protected['question-statistics.json'], 'Untracked question statistics changed.');

echo "[PASS] 20 exact pilot IDs and versions, 41 citation/source registry reconciliations, 40/1 content-examination states, bounded Annex inspection, pending-only real cohort, private scoped reviewer packet, read-only HTTP behavior, and protected storage hashes.\n";
