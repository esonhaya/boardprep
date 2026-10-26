<?php

declare(strict_types=1);

require_once __DIR__ . '/Support/StaticStorageFixture.php';

$root = dirname(__DIR__, 2);
$fixture = StaticStorageFixture::create($root, 'batch717-academic-gate-closure');
$cleanup = true;
register_shutdown_function(static function () use ($fixture, &$cleanup): void {
    if (!$cleanup) return;
    $real = realpath($fixture);
    $temporary = realpath(sys_get_temp_dir());
    if (!is_string($real) || !is_string($temporary) || !str_starts_with($real, $temporary . DIRECTORY_SEPARATOR)
        || !str_starts_with(basename($real), 'boardprep-batch717-academic-gate-closure-')) return;
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
    if (!$condition) throw new RuntimeException('Batch 717 academic gate closure failed: ' . $message);
};
$readJson = static function (string $path) use ($root): array {
    $contents = file_get_contents($root . '/' . $path);
    if (!is_string($contents)) throw new RuntimeException('Cannot read ' . $path);
    $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($decoded)) throw new RuntimeException('Invalid JSON in ' . $path);
    return $decoded;
};

$protected = [
    'questions.json' => '73cdc775cd7240b57898868d67902d833db1171f947c8dcc89081ddaf46fb4be',
    'question-eligibility.json' => '611f01ca29421c0c890c351c503ab262e87bc551269d78427b9a0889260933a4',
    'sources.json' => '7c640636dcd3ddd0f865106ba4506a5af5dcd4a02a2074c065f695da5ef2c271',
    'study-question-links.json' => '0e0aebad84afd09e38e477f277bb8086e52eb9b4a2e591c6de41118060a5663d',
];
$protected['question-statistics.json'] = '4f53cda18c2baa0c0354bb5f9a3ecbe5ed12ab4d8e11ba873c2f11161202b945';
foreach ($protected as $file => $expectedHash) {
    $path = $root . '/storage/' . $file;
    $assert(is_file($path) && hash_equals($expectedHash, hash_file('sha256', $path) ?: ''), 'Protected file hash changed: ' . $file);
}

$evidence = $readJson('docs/reports/batch-717-evidence-verification.json');
$readiness = $readJson('docs/reports/batch-717-cohort-readiness.json');
$matrix = $readJson('docs/reports/batch-716-source-evidence-matrix.json');
$expectedPilot = ['9124','9125','9126','9127','9128','9140','9164','9200','9204','9210','9228','9238','9250','9280','9290','9302','9320','9348','9360','9378'];
$assert(($evidence['schema'] ?? '') === 'boardprep.batch-717-evidence-verification.v1'
    && count($evidence['items'] ?? []) === 20 && count($evidence['citation_rows'] ?? []) === 41,
    'B717 evidence package must contain 20 version-bound items and 41 citations.');
$assert(($readiness['schema'] ?? '') === 'boardprep.batch-717-cohort-readiness.v1'
    && count($readiness['items'] ?? []) === 276 && (int) ($readiness['summary']['cohort_total'] ?? 0) === 276,
    'Readiness inventory must cover all 276 proposals.');
$assert((int) ($evidence['summary']['content_examined_rows'] ?? 0) === 41
    && (int) ($evidence['summary']['qualified_source_reviews'] ?? -1) === 0
    && (int) ($evidence['summary']['academic_approvals'] ?? -1) === 0
    && (int) ($evidence['summary']['real_reviewer_grants'] ?? -1) === 0
    && (int) ($evidence['summary']['real_imports'] ?? -1) === 0
    && (int) ($evidence['summary']['machine_claim_verified_rows'] ?? -1) === 0,
    'Machine evidence was promoted to human review, approval, or import.');
$assert((int) ($evidence['summary']['machine_claim_status_counts']['CLAIM_CANDIDATE'] ?? 0) === 15
    && (int) ($evidence['summary']['machine_claim_status_counts']['INSUFFICIENT_EVIDENCE'] ?? 0) === 5
    && (int) ($evidence['summary']['machine_claim_status_counts']['LOCATOR_CONFIRMED'] ?? 0) === 21,
    'Machine claim states are not the expected conservative, unverified distribution.');
$assert(\App\Services\Question\Batch715AcademicPilotService::batch717SourceSnapshotCurrent(), 'B717 source registry snapshot is stale.');

$sourceRows = array_values(array_filter((array) ($evidence['citation_rows'] ?? []), 'is_array'));
$assert(count($sourceRows) === 41 && count(array_unique(array_map(static fn(array $row): string => (string) ($row['citation_id'] ?? ''), $sourceRows))) === 41,
    'Citation identifiers are missing or duplicated.');
$assert(count(array_unique(array_map(static fn(array $row): string => (string) ($row['source_id'] ?? ''), $sourceRows))) === 12,
    'Expected exactly 12 reconciled unique sources.');
$allowedStates = ['NOT_EXAMINED','LOCATOR_CONFIRMED','CLAIM_CANDIDATE','CONFLICT_DETECTED','INSUFFICIENT_EVIDENCE','AWAITING_EXPERT_REVIEW'];
foreach ($sourceRows as $row) {
    $assert(!empty($row['url_reachable']) && !empty($row['source_identity_confirmed']) && !empty($row['content_examined']),
        'Reachability, source identity and content examination were collapsed or missing for ' . (string) ($row['citation_id'] ?? 'unknown'));
    $assert(in_array((string) ($row['machine_claim_evidence_status'] ?? ''), $allowedStates, true)
        && empty($row['machine_claim_verified'])
        && ($row['independent_expert_source_review'] ?? '') === 'PENDING_QUALIFIED_REVIEWER'
        && ($row['academic_approval'] ?? '') === 'NOT_APPROVED',
        'Citation state falsely implies item support or a human decision.');
}

$canonicalHash = static function (mixed $value) use (&$canonicalHash): mixed {
    if (!is_array($value)) return $value;
    if (array_is_list($value)) return array_map($canonicalHash, $value);
    ksort($value, SORT_STRING);
    foreach ($value as $key => $child) $value[$key] = $canonicalHash($child);
    return $value;
};
$sourceRegistry = $readJson('storage/sources.json');
$sourceById = [];
foreach ($sourceRegistry as $source) if (is_array($source)) $sourceById[(string) ($source['id'] ?? '')] = $source;
$proposalWorkflow = new \App\Services\Question\AcademicReviewWorkflowService(
    \App\Core\App::storage(), \App\Services\Question\AcademicReviewProposalService::all(),
    new \App\Services\Question\AcademicReviewerAuthorizationService(\App\Core\App::storage()), $fixture
);
$seenQuestions = [];
$nestedCitationCount = 0;
foreach ((array) $evidence['items'] as $item) {
    $qid = (string) ($item['question_id'] ?? '');
    $version = (string) ($item['question_version_sha256'] ?? '');
    $assert(!isset($seenQuestions[$qid]) && in_array($qid, $expectedPilot, true), 'Unexpected or duplicate pilot item ' . $qid);
    $seenQuestions[$qid] = true;
    $proposalId = (string) ($item['proposal_id'] ?? '');
    $assert(hash_equals($proposalWorkflow->version($proposalId), $version), 'Evidence version mismatch for Q' . $qid);
    $assert(($item['academic_decision_status'] ?? '') === 'PENDING_REVIEW'
        && ($item['source_review_status'] ?? '') === 'PENDING_QUALIFIED_REVIEWER'
        && ($item['import_status'] ?? '') === 'NOT_IMPORT_READY'
        && empty($item['machine_claim_verified']), 'Pilot item is no longer pending for Q' . $qid);
    $proposal = $proposalWorkflow->findProposal($proposalId);
    $assert(is_array($proposal) && (string) ($proposal['question_id'] ?? '') === $qid, 'Proposal source missing for Q' . $qid);
    $assert(\App\Core\App::storage()->find('questions', $qid) === null, 'Proposal entered canonical bank: Q' . $qid);
    foreach ((array) ($item['citation_rows'] ?? []) as $citation) {
        $nestedCitationCount++;
        $sourceId = (string) ($citation['source_id'] ?? '');
        $source = $sourceById[$sourceId] ?? null;
        $sourceHash = is_array($source)
            ? hash('sha256', json_encode($canonicalHash($source), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR))
            : '';
        $assert(is_array($source) && hash_equals($sourceHash, (string) ($citation['source_registry']['current_record_sha256'] ?? '')),
            'Current source registry identity/hash mismatch for Q' . $qid . ' / ' . $sourceId);
        $assert((string) ($citation['question_version_sha256'] ?? '') === $version,
            'Citation is bound to a different question version for Q' . $qid);
    }
}
$assert(array_keys($seenQuestions) !== [] && count($seenQuestions) === 20 && $nestedCitationCount === 41, 'Pilot item and nested citation set is incomplete.');
$assert(array_values(array_map(static fn(array $item): string => (string) $item['question_id'], $evidence['items'])) === $expectedPilot,
    'Pilot order or selected IDs changed.');

$q9228 = null;
foreach ((array) $evidence['items'] as $item) if (($item['question_id'] ?? '') === '9228') $q9228 = $item;
$assert(is_array($q9228) && ($q9228['machine_claim_status'] ?? '') === 'CLAIM_CANDIDATE', 'Q9228 claim candidate missing.');
$assert(in_array('Respond to a child-protection concern calmly, document observable facts, and use school procedures.', (array) ($q9228['rationale_claims_for_expert'] ?? []), true),
    'Q9228 specific procedural rationale claim is missing from reviewer questions.');
$q9228DepedRows = array_values(array_filter((array) $q9228['citation_rows'], static fn(array $row): bool => ($row['source_id'] ?? '') === 'deped-do-40-2012-child-protection'));
$assert(count($q9228DepedRows) === 1 && !empty($q9228DepedRows[0]['content_examined'])
    && ($q9228DepedRows[0]['independent_expert_source_review'] ?? '') === 'PENDING_QUALIFIED_REVIEWER',
    'Targeted DO40 OCR was not recorded separately from expert review.');
$extras = (array) ($evidence['supplemental_source_inspections'] ?? []);
$assert(count($extras['deped_do_40_s_2012']['inspected_pages'] ?? []) === 6
    && count($extras['deped_do_40_s_2012']['passage_contexts'] ?? []) === 6, 'DO40 bounded OCR/page evidence is incomplete.');
$irr = (array) ($extras['deped_2025_revised_anti_bullying_irr'] ?? []);
$assert(!empty($irr['supplemental_not_in_b695_source_snapshot']) && count($irr['inspected_pages'] ?? []) === 4,
    'Supplemental 2025 IRR was treated as original citation or not page checked.');
$do006 = (array) ($extras['deped_do_006_s_2026'] ?? []);
$assert(($do006['machine_status'] ?? '') === 'NOT_EXAMINED' && empty($do006['whole_document_downloaded'])
    && empty($do006['content_examined']) && (int) ($do006['maximum_download_bytes'] ?? 0) === 18000000,
    'Oversized DO006 was improperly represented as examined.');

$prc = (array) ($extras['prc_resolution_20_s_2025_annex'] ?? []);
$prcBounded = (array) ($prc['bounded_sparse_retrieval'] ?? []);
$rangeLogPath = '/data/data/com.termux/files/usr/tmp/b717-prc-annex-range-log.json';
$rangeLog = json_decode((string) file_get_contents($rangeLogPath), true, 512, JSON_THROW_ON_ERROR);
$assert(is_array($rangeLog) && count($rangeLog['ranges'] ?? []) === 66
    && (int) ($rangeLog['aggregate_bytes'] ?? 0) === 17054594 && (int) ($rangeLog['aggregate_bytes'] ?? 0) < 18000000
    && count($prcBounded['extracted_page_records'] ?? []) === 14 && ($prc['full_document_sha256'] ?? null) === null,
    'PRC Annex page extraction exceeded its cap or was represented as a complete-document download.');
foreach ((array) $rangeLog['ranges'] as $range) {
    $assert((int) ($range['status'] ?? 0) === 206 && (int) ($range['bytes'] ?? PHP_INT_MAX) <= 262144
        && preg_match('/\A[0-9a-f]{64}\z/', (string) ($range['sha256'] ?? '')) === 1,
        'Invalid bounded PRC range record.');
}
$architecture = (array) ($extras['prc_architecture_program_recheck'] ?? []);
$assert(empty($architecture['current_or_future_detailed_architecture_program_found'])
    && in_array('9124', (array) ($architecture['question_ids_blocked'] ?? []), true)
    && in_array('9127', (array) ($architecture['question_ids_blocked'] ?? []), true)
    && str_contains((string) ($architecture['october_2026_schedule_correction'] ?? ''), 'Naval Architects'),
    'Architecture currentness or October schedule distinction is missing.');

$readinessSummary = (array) ($readiness['summary'] ?? []);
$assert((int) ($readinessSummary['limited_source_scope_proposals'] ?? 0) === 6
    && (array) ($readinessSummary['limited_source_scope_question_ids'] ?? []) === ['9228','9229','9230','9231','9232','9233']
    && (int) ($readinessSummary['historical_exam_program_proposals'] ?? 0) === 4
    && (int) ($readinessSummary['topic_reference_instances_unregistered'] ?? 0) === 270
    && (int) ($readinessSummary['concept_reference_instances_unregistered'] ?? 0) === 276
    && (int) ($readinessSummary['study_item_relationships']['EXACT_SECTION_CANDIDATE_UNLINKED'] ?? 0) === 18
    && (int) ($readinessSummary['study_item_relationships']['MISSING_SECTION_OPTIONAL'] ?? 0) === 258
    && (int) ($readinessSummary['taxonomy_records_activated'] ?? -1) === 0
    && (int) ($readinessSummary['study_links_activated'] ?? -1) === 0,
    '276-item dependency inventory or no-activation guard is incorrect.');
foreach ((array) $readiness['items'] as $item) {
    $qid = (string) ($item['question_id'] ?? '');
    $assert(($item['primary_disposition'] ?? '') === 'PENDING_ACADEMIC_REVIEW'
        && ($item['academic_state'] ?? '') === 'PENDING_REVIEW'
        && !empty($item['pilot_member']) === in_array($qid, $expectedPilot, true),
        'Cohort primary state or pilot marker is incorrect for Q' . $qid);
}

// The private production inspector may read this report only under a synthetic,
// narrowly scoped grant in isolated JSON storage. It must remain read-only.
require_once dirname(__DIR__) . '/Doctor/Project/BoardPrep/Simulation/HttpSimulator.php';
$accountId = 'account-' . bin2hex(random_bytes(16));
$learnerId = 'account-learner-' . bin2hex(random_bytes(16));
$accounts = new \App\Services\Accounts\AccountRepository($fixture . '/private/learner-accounts');
$assert($accounts->create([
    'account_id' => $accountId, 'learner_id' => $learnerId,
    'username' => 'b717-fixture-' . bin2hex(random_bytes(5)),
    'password_hash' => password_hash('fixture-only-password', PASSWORD_DEFAULT),
    'status' => 'active', 'auth_version' => 1,
    'created_at' => gmdate(DATE_ATOM), 'updated_at' => gmdate(DATE_ATOM),
]), 'Unable to create isolated synthetic account.');
\App\Core\App::storage()->create('academic-reviewer-authorizations', [
    'id' => 'b717-synthetic-grant-' . bin2hex(random_bytes(4)),
    'account_id' => $accountId, 'authorization_status' => 'ACTIVE',
    'qualification_type' => 'SUBJECT_EXPERT', 'qualification_reference' => 'SYNTHETIC_TEST_ONLY',
    'authorized_by' => 'SYNTHETIC_TEST_ONLY_OWNER', 'authorized_at' => gmdate(DATE_ATOM),
    'expires_at' => '2099-12-31T23:59:59+00:00',
    'scopes' => [[
        'board_id' => 'let', 'subject_id' => 'early-childhood-education',
        'capabilities' => ['REVIEW','SOURCE_REVIEW'],
    ]],
]);
$http = new \Tools\Doctor\Project\BoardPrep\Simulation\HttpSimulator($root . '/public/index.php');
$sessionId = 'b717fixture' . bin2hex(random_bytes(8));
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
    'learner_identity' => $learnerId, '_csrf_token' => bin2hex(random_bytes(32)),
];
session_write_close();
$before = [
    'questions' => \App\Core\App::storage()->all('questions'),
    'workflow' => \App\Core\App::storage()->all('academic-proposal-workflow'),
    'decisions' => \App\Core\App::storage()->all('academic-review-decisions'),
    'sources' => \App\Core\App::storage()->all('academic-source-reviews'),
    'links' => \App\Core\App::storage()->all('study-question-links'),
    'attempts' => \App\Core\App::storage()->all('attempts'),
];
$detail = $http->request('GET', '/developer/academic-review/proposal', ['id' => 'batch695-9228'], [], [], ['PHPSESSID' => $sessionId]);
$assert(($detail['status'] ?? 0) === 200, 'Synthetic scoped reviewer could not open Q9228 inspector: ' . (string) ($detail['stderr'] ?? ''));
$html = (string) ($detail['output'] ?? '');
foreach ([
    'Batch 717 claim-level evidence', 'Version-bound citations', 'printed pp. 2',
    '2025_REVISED_ANTI_BULLYING_IRR', 'Supplemental context', 'not examined', 'Independent source review',
    'document observable facts', 'No change, approval, assignment', 'DO 40',
] as $needle) {
    $assert(str_contains($html, $needle), 'Private B717 reviewer inspector omitted expected content: ' . $needle);
}
$assert(!str_contains($html, 'machine verified</dt><dd>yes') && !str_contains($html, 'Import this approved proposal'),
    'Inspector falsely promoted machine support or exposed import before decisions.');
$anonymous = $http->request('GET', '/developer/academic-review/proposal', ['id' => 'batch695-9228']);
$assert(($anonymous['status'] ?? 0) === 401, 'Anonymous access to evidence inspector was allowed.');
$foreign = $http->request('GET', '/developer/academic-review/proposal', ['id' => 'batch695-9124'], [], [], ['PHPSESSID' => $sessionId]);
$assert(($foreign['status'] ?? 0) === 403, 'Cross-subject/scope reviewer access was allowed.');
$csrf = $http->request('POST', '/developer/academic-review/decision', [], [
    'proposal_id' => 'batch695-9228', 'decision' => 'APPROVE', 'rationale' => 'synthetic forged decision',
]);
$assert(($csrf['status'] ?? 0) === 403, 'Mutation without CSRF was not rejected.');
$assert(\App\Core\App::storage()->all('questions') === $before['questions']
    && \App\Core\App::storage()->all('academic-proposal-workflow') === $before['workflow']
    && \App\Core\App::storage()->all('academic-review-decisions') === $before['decisions']
    && \App\Core\App::storage()->all('academic-source-reviews') === $before['sources']
    && \App\Core\App::storage()->all('study-question-links') === $before['links']
    && \App\Core\App::storage()->all('attempts') === $before['attempts'], 'Read-only/denied HTTP acceptance mutated isolated workflow or learner data.');

foreach ($protected as $file => $expectedHash) {
    $assert(hash_equals($expectedHash, hash_file('sha256', $root . '/storage/' . $file) ?: ''), 'Protected production file changed during the B717 test: ' . $file);
}
echo "[PASS] B717 41-row source evidence and 276-item readiness contracts, 20 exact versions, bounded official-source findings, cautious claim states, private version-bound inspector, anonymous/foreign/CSRF denial, and unchanged production learner/canonical data.\n";
