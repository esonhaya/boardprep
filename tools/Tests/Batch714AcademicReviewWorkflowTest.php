<?php

declare(strict_types=1);

require_once __DIR__ . '/Support/StaticStorageFixture.php';

$root = dirname(__DIR__, 2);
$protectedQuestionBankHash = hash_file('sha256', $root . '/storage/questions.json');
$protectedEligibilityHash = hash_file('sha256', $root . '/storage/question-eligibility.json');
$fixture = StaticStorageFixture::create($root, 'batch714-academic-review');
 $cleanupFixtureOnShutdown = true;
register_shutdown_function(static function () use ($fixture, &$cleanupFixtureOnShutdown): void {
    if (!$cleanupFixtureOnShutdown) return;
    $real = realpath($fixture);
    $temporary = realpath(sys_get_temp_dir());
    if (!is_string($real) || !is_string($temporary)
        || !str_starts_with($real, $temporary . DIRECTORY_SEPARATOR)
        || !str_contains(basename($real), 'boardprep-batch714-academic-review-')) return;
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($real, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
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
    if (!$condition) throw new RuntimeException('Batch 714 academic workflow failed: ' . $message);
};
$expectFailure = static function (callable $operation, string $messagePart, string $name) use ($assert): Throwable {
    try {
        $operation();
    } catch (Throwable $exception) {
        $assert(str_contains($exception->getMessage(), $messagePart), $name . ' failed for the wrong reason: ' . $exception->getMessage());
        return $exception;
    }
    throw new RuntimeException('Batch 714 academic workflow failed: ' . $name . ' was accepted.');
};
$cleanupOwned = static function (string $path, string $prefix) use ($assert): void {
    $real = realpath($path);
    $temporary = realpath(sys_get_temp_dir());
    $assert(is_string($real) && is_string($temporary)
        && str_starts_with($real, $temporary . DIRECTORY_SEPARATOR)
        && str_starts_with(basename($real), $prefix), 'Refusing to remove a path outside the owned test fixture.');
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($real, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($iterator as $entry) {
        if ($entry->isDir() && !$entry->isLink()) @rmdir($entry->getPathname());
        else @unlink($entry->getPathname());
    }
    @rmdir($real);
};

$jsonStorage = new \App\Storage\JsonStorage($fixture);
@mkdir($fixture . '/runtime', 0700, true);
$sqlitePath = $fixture . '/runtime/batch714-review.sqlite';
$sqlitePdo = new PDO('sqlite:' . $sqlitePath, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$sqliteMigrations = (new \App\Database\MigrationRunner($root . '/database/migrations'))->migrate($sqlitePdo);
$assert($sqliteMigrations === ['001_create_storage_records'], 'The isolated SQLite fixture did not apply the repository migration.');
$sqliteStorage = new \App\Storage\SqliteStorage($sqlitePdo);
$storage = new \App\Storage\StorageRouter($jsonStorage, [
    'attempts' => $sqliteStorage,
    'weakness' => $sqliteStorage,
    'flashcard_state' => $sqliteStorage,
    'question-statistics' => $sqliteStorage,
]);
\App\Core\App::container()->bind(\App\Contracts\StorageInterface::class, static fn() => $storage);
$questionRepository = new \App\Repositories\QuestionRepository($storage);
$attemptRepository = new \App\Repositories\AttemptRepository($storage);
\App\Core\App::container()->bind(\App\Repositories\QuestionRepository::class, static fn() => $questionRepository);
\App\Core\App::container()->bind(\App\Repositories\AttemptRepository::class, static fn() => $attemptRepository);

// Test-only readiness fixture permits a real published, scoped section link.
$readinessPath = $fixture . '/source-readiness-let.json';
$readiness = json_decode((string) file_get_contents($readinessPath), true, 512, JSON_THROW_ON_ERROR);
$readinessChanged = false;
foreach ($readiness as &$readinessRecord) {
    if (!is_array($readinessRecord) || ($readinessRecord['leaf_id'] ?? '') !== 'english-inference-evidence') continue;
    $readinessRecord['study_material_ids'] = ['let-english-reading-language-and-composition'];
    $readinessRecord['study_material_count'] = 1;
    $readinessRecord['evidence_hash'] = \App\Services\RepositoryHealth\Readiness\SourceReadinessRecord::evidenceHash($readinessRecord);
    $readinessChanged = true;
}
unset($readinessRecord);
$assert($readinessChanged, 'The isolated test fixture could not locate its scoped readiness record.');
file_put_contents($readinessPath, json_encode($readiness, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n", LOCK_EX);

$sourceId = 'purdue-owl-writing';
$source = $storage->find('sources', $sourceId);
$assert(is_array($source), 'The test source reference must resolve from the isolated source registry.');
$reviewerId = 'account-' . bin2hex(random_bytes(16));
$importerId = 'account-' . bin2hex(random_bytes(16));
$developerId = 'account-' . bin2hex(random_bytes(16));
$unqualifiedId = 'account-' . bin2hex(random_bytes(16));
$foreignScopeId = 'account-' . bin2hex(random_bytes(16));
$reviewCapabilities = ['REVIEW', 'SOURCE_REVIEW', 'TAXONOMY_RECONCILE', 'STUDY_LINK', 'EDIT_PROPOSAL'];
$now = gmdate(DATE_ATOM);
$future = '2099-12-31T23:59:59+00:00';
$grant = static function (string $id, string $accountId, array $capabilities, string $qualification = 'SUBJECT_EXPERT', string $qualificationReference = 'test-only-synthetic-qualification'): array {
    return [
        'id' => $id, 'account_id' => $accountId, 'authorization_status' => 'ACTIVE',
        'qualification_type' => $qualification, 'qualification_reference' => $qualificationReference,
        'authorized_by' => 'test-only-owner-fixture', 'authorized_at' => gmdate(DATE_ATOM),
        'expires_at' => '2099-12-31T23:59:59+00:00',
        'scopes' => [['board_id' => 'let', 'subject_id' => 'english', 'capabilities' => $capabilities]],
    ];
};
$grants = [
    $grant('grant-test-reviewer', $reviewerId, $reviewCapabilities),
    $grant('grant-test-importer', $importerId, ['IMPORT']),
    $grant('grant-test-developer-role', $developerId, ['REVIEW'], 'DEVELOPER', 'developer-role-is-not-a-qualification'),
    $grant('grant-test-no-credential', $unqualifiedId, ['REVIEW'], 'SUBJECT_EXPERT', ''),
    $grant('grant-test-foreign', $foreignScopeId, ['REVIEW']),
];
$grants[array_key_last($grants)]['scopes'][0]['subject_id'] = 'mathematics';
$storage->replace('academic-reviewer-authorizations', $grants);
$authorization = new \App\Services\Question\AcademicReviewerAuthorizationService($storage);
$assert($authorization->allows($reviewerId, 'let', 'english', 'REVIEW'), 'A valid scoped subject-expert grant was not recognized.');
$assert(!$authorization->allows($developerId, 'let', 'english', 'REVIEW'), 'A developer-role label incorrectly qualified an account.');
$assert(!$authorization->allows($unqualifiedId, 'let', 'english', 'REVIEW'), 'A grant without a credential reference was accepted.');

$proposalFactory = static function (string $id, string $questionId, string $stem) use ($sourceId, $source): array {
    return [
        'id' => $id,
        'question_id' => $questionId,
        'board_id' => 'let',
        'subject_id' => 'english',
        'domain_id' => 'english-reading-comprehension',
        'topic_id' => 'english-inference',
        'concept_id' => 'english-inference-evidence',
        'proposed_question' => [
            'stem' => $stem,
            'choices' => ['The evidence proves a cause.', 'The evidence supports only the stated inference.', 'The evidence is irrelevant.', 'No conclusion can be made.'],
            'proposed_answer' => 'The evidence supports only the stated inference.',
            'explanation' => 'A supported inference stays within what the passage and its cited details establish.',
            'difficulty' => 'medium',
        ],
        'source_ids' => [$sourceId],
        'source_evidence' => [[
            'source_id' => $sourceId,
            'source_record_snapshot' => $source,
            'source_evidence_status' => 'REVIEW_REQUIRED',
            'limitations' => ['Synthetic workflow-test proposal; no real B695 claim is being approved.'],
            'test_fixture_only' => true,
        ]],
        'proposal_reason' => ['specific_claim_to_review' => 'Test fixture only: verify the claim against the cited item-level source.'],
        'provenance' => ['original_source_branch' => 'test-only', 'original_source_commit_sha' => str_repeat('a', 40)],
        'study_relationship' => ['material_id' => 'let-english-reading-language-and-composition', 'section_id' => 'reading-claim-and-evidence'],
        'test_fixture_only' => true,
    ];
};
$proposals = [
    $proposalFactory('b714-test-revision', 'synthetic-b714-revision', 'TEST ONLY: Which conclusion stays within the cited synthetic passage?'),
    $proposalFactory('b714-test-rollback', 'synthetic-b714-rollback', 'TEST ONLY: Which reading claim matches the synthetic evidence?'),
    $proposalFactory('b714-test-concurrent', 'synthetic-b714-concurrent', 'TEST ONLY: Which inference is limited to the reported synthetic evidence?'),
    $proposalFactory('b714-test-collision', '1', 'TEST ONLY: Which conclusion stays inside the synthetic passage?'),
];
$workflow = new \App\Services\Question\AcademicReviewWorkflowService($storage, $proposals, $authorization, $fixture);
$versionOf = static fn(string $id): string => $workflow->version($id);

// The real pilot package is read-only: its exact versions bind to production proposal snapshots, not synthetic approvals.
$pilotCohort = \App\Services\Question\Batch715AcademicPilotService::cohort();
$pilotReadiness = \App\Services\Question\Batch715AcademicPilotService::readiness();
$realProposalWorkflow = new \App\Services\Question\AcademicReviewWorkflowService(
    $storage, \App\Services\Question\AcademicReviewProposalService::all(), $authorization, $fixture
);
$assert(count($pilotCohort['items'] ?? []) === 20 && count($pilotReadiness['items'] ?? []) === 20, 'The bounded real pilot cohort or its evidence dossier is incomplete.');
$readinessByQuestion = [];
foreach ($pilotReadiness['items'] as $evidenceItem) $readinessByQuestion[(string) $evidenceItem['question_id']] = $evidenceItem;
$pilotBoards = $pilotSubjects = [];
foreach ($pilotCohort['items'] as $pilotItem) {
    $pilotQuestionId = (string) ($pilotItem['question_id'] ?? '');
    $pilotBoards[(string) ($pilotItem['board']['id'] ?? '')] = true;
    $pilotSubjects[(string) ($pilotItem['subject']['id'] ?? '')] = true;
    $assert(\App\Services\Question\Batch715AcademicPilotService::isSelected($pilotQuestionId), 'Pilot membership lookup omitted an item.');
    $assert($storage->find('questions', $pilotQuestionId) === null, 'A B695 pilot proposal was present in the canonical question collection.');
    $assert(($pilotItem['review_state'] ?? '') === 'PENDING_REVIEW'
        && ($pilotItem['production_status'] ?? '') === 'NONCANONICAL_NOT_IN_LEARNER_POOLS', 'A real pilot item was promoted out of its pending noncanonical state.');
    $assert(hash_equals((string) ($pilotItem['question_version'] ?? ''), $realProposalWorkflow->version((string) ($pilotItem['proposal_id'] ?? ''))), 'Pilot evidence is not bound to the current exact proposal version for ' . $pilotQuestionId . '.');
    $evidenceItem = $readinessByQuestion[$pilotQuestionId] ?? null;
    $assert(is_array($evidenceItem) && ($evidenceItem['academic_approval'] ?? '') === 'NOT_APPROVED', 'Pilot dossier implies an academic approval.');
    foreach ((array) ($evidenceItem['sources'] ?? []) as $sourceEvidence) {
        foreach (['url_reachable','source_identity_confirmed','content_examined'] as $evidenceState) {
            $assert(array_key_exists($evidenceState, $sourceEvidence), 'Source dossier omitted the distinct evidence state ' . $evidenceState . '.');
        }
        $assert(($sourceEvidence['expert_source_review'] ?? '') === 'PENDING_QUALIFIED_REVIEWER', 'Machine-prepared source evidence claimed qualified review.');
    }
    $study = (array) ($pilotItem['study_relationships'] ?? []);
    if (($study['status'] ?? '') === 'EXACT_SECTION_MATCH') {
        $material = \App\Services\Study\StudyLibraryService::find((string) ($study['material_id'] ?? ''));
        $lesson = is_array($material['lesson'] ?? null) ? $material['lesson'] : [];
        $sectionIds = array_map(static fn(array $section): string => (string) ($section['id'] ?? ''), array_values(array_filter((array) ($lesson['sections'] ?? []), 'is_array')));
        $assert(in_array((string) ($study['section_id'] ?? ''), $sectionIds, true), 'A proposed exact study section is not present in the published Study Library.');
        $assert(\App\Services\Study\StudyLibraryService::forQuestion($pilotQuestionId, (string) ($pilotItem['board']['id'] ?? ''), (string) ($pilotItem['subject']['id'] ?? '')) === [], 'A pilot proposal unexpectedly had an active study link before review.');
    }
}
$assert(count($pilotBoards) > 1 && count($pilotSubjects) >= 5, 'Pilot selection does not provide cross-board and representative subject coverage.');

// Authentication, assignment, state transitions, revision invalidation, and audit history.
$revisionId = 'b714-test-revision';
$revisionVersion = $versionOf($revisionId);
$expectFailure(fn() => $workflow->assign($revisionId, 'account-unprivileged', $revisionVersion), 'REVIEWER_NOT_AUTHORIZED', 'unauthorized review');
$expectFailure(fn() => $workflow->assign($revisionId, $foreignScopeId, $revisionVersion), 'REVIEWER_NOT_AUTHORIZED', 'foreign-scope review');
$expectFailure(fn() => $workflow->assign($revisionId, $developerId, $revisionVersion), 'REVIEWER_NOT_AUTHORIZED', 'developer-role review');
$expectFailure(fn() => $workflow->assign($revisionId, $unqualifiedId, $revisionVersion), 'REVIEWER_NOT_AUTHORIZED', 'unqualified reviewer');
$expectFailure(fn() => $workflow->assign($revisionId, $reviewerId, str_repeat('0', 64)), 'STALE_PROPOSAL_VERSION', 'stale proposal version');
$workflow->assign($revisionId, $reviewerId, $revisionVersion);
$workflow->startReview($revisionId, $reviewerId, $revisionVersion);
$workflow->decide($revisionId, $reviewerId, [
    'decision' => 'REQUEST_REVISION', 'proposal_version' => $revisionVersion,
    'rationale' => 'Test fixture requests a clearer explanation of the evidence boundary.',
]);
$expectFailure(fn() => $workflow->decide($revisionId, $reviewerId, [
    'decision' => 'REQUEST_REVISION', 'proposal_version' => $revisionVersion,
    'rationale' => 'Duplicate test decision must not append a second transition.',
]), 'DUPLICATE_DECISION', 'duplicate decision');
$revisionRecord = $workflow->record($revisionId);
$assert($revisionRecord['state'] === 'REVISION_REQUESTED' && count($revisionRecord['decisions']) === 3, 'Revision request and assignment history were not preserved.');
$revisedContent = $workflow->currentProposal($revisionId)['proposed_question'];
$revisedContent['explanation'] .= ' This test-only revision explicitly limits the inference.';
$workflow->recordRevision($revisionId, $reviewerId, $revisionVersion, $revisedContent, 'Test-only revision preserves the original proposal and adds provenance.');
$assert($workflow->record($revisionId)['state'] === 'PENDING_REVIEW', 'Recording a revision did not restart independent review.');
$assert(count($workflow->record($revisionId)['decisions']) === 4 && count($workflow->record($revisionId)['revisions']) === 1, 'Revision history overwrote an earlier decision.');
$assert($workflow->version($revisionId) !== $revisionVersion, 'Material revision did not change the proposal version.');
$revisionPreflight = $workflow->importPreflight($revisionId, $importerId);
$assert(in_array('ACADEMIC_APPROVAL_REQUIRED', $revisionPreflight['blocked'], true)
    && in_array('CURRENT_VERSION_APPROVAL_REQUIRED', $revisionPreflight['blocked'], true), 'A prior-version approval was allowed to apply to a revision.');
$revisedVersion = $workflow->version($revisionId);
$workflow->assign($revisionId, $reviewerId, $revisedVersion);
$workflow->startReview($revisionId, $reviewerId, $revisedVersion);
$missingRegulatoryCheck = ['decision' => 'APPROVE', 'rationale' => 'Synthetic negative test leaves required LET scope currentness unchecked.', 'confirmations' => array_fill_keys(['question_accuracy','correct_answer','explanation_correctness','exam_relevance','subject_classification','adequate_source_support','source_currentness'], true)];
$expectFailure(fn() => $workflow->decide($revisionId, $reviewerId, ['proposal_version' => $revisedVersion] + $missingRegulatoryCheck), 'APPROVAL_CONFIRMATION_REQUIRED:regulatory_scope_currentness', 'missing LET scope currentness confirmation');

// Read-only methods do not persist review decisions or proposals.
$workflowRowsBeforeRead = $storage->all('academic-proposal-workflow');
$workflow->page(['board' => 'let', 'subject' => 'english'], 1, 3, $reviewerId);
$workflowRowsAfterRead = $storage->all('academic-proposal-workflow');
$assert($workflowRowsBeforeRead === $workflowRowsAfterRead, 'Queue reads mutated academic workflow records.');

// Taxonomy mapping refuses unregistered labels; owner proposals never enter active taxonomy.
$invalidTaxonomyProposal = $proposalFactory('b714-invalid-taxonomy', 'synthetic-b714-taxonomy', 'TEST ONLY: Invalid taxonomy fixture should never import.');
$taxonomyService = new \App\Services\Question\AcademicReviewWorkflowService($storage, array_merge($proposals, [$invalidTaxonomyProposal]), $authorization, $fixture);
$expectFailure(fn() => $taxonomyService->reconcileTaxonomy('b714-invalid-taxonomy', $reviewerId, $taxonomyService->version('b714-invalid-taxonomy'), [
    'domain_id' => 'english-reading-comprehension', 'topic_id' => 'missing-unregistered-topic', 'concept_id' => 'missing-unregistered-concept',
], 'Test-only attempt to map to nonexistent approved taxonomy.'), 'TAXONOMY_MAPPING_INVALID', 'unsupported taxonomy mapping');
$proposalCountBefore = count($storage->all('academic-proposal-workflow'));
$taxonomyService->proposeTaxonomyEntry('b714-invalid-taxonomy', $reviewerId, $taxonomyService->version('b714-invalid-taxonomy'), 'TOPIC', 'Test-only topic proposal', 'english-reading-comprehension', 'Test-only taxonomy label request with provenance.');
$assert(count($storage->all('academic-proposal-workflow')) === $proposalCountBefore + 1, 'A governed taxonomy proposal was not kept in the review store.');
$assert(\App\Services\Shared\TaxonomyStorageService::find('taxonomy/topics', 'test-only-topic-proposal') === null, 'A pending taxonomy request changed active taxonomy.');

$taxonomy = [
    'board_id' => 'let', 'subject_id' => 'english', 'domain_id' => 'english-reading-comprehension',
    'topic_id' => 'english-inference', 'concept_id' => 'english-inference-evidence', 'required_depth' => 2,
];
$sourceReviewInput = [
    'status' => 'ITEM_LEVEL_CLAIM_SUPPORTED',
    'source_identity_verified' => true,
    'source_content_examined' => true,
    'source_currentness_confirmed' => true,
    'url_reachability' => 'NOT_CHECKED',
    'source_locator' => 'TEST FIXTURE ONLY: synthetic test evidence locator',
    'source_scope' => 'english-inference-evidence',
    'rationale' => 'Synthetic source review exercises the independent source gate only.',
    'limitations' => 'TEST FIXTURE ONLY; not an academic finding about a real question.',
];
$approvalInput = [
    'decision' => 'APPROVE',
    'rationale' => 'Synthetic approval exists only inside this isolated workflow test fixture.',
    'confirmations' => array_fill_keys(['question_accuracy','correct_answer','explanation_correctness','exam_relevance','subject_classification','adequate_source_support','source_currentness','regulatory_scope_currentness'], true),
];
$prepare = static function (string $proposalId, bool $withSourceReview = true) use ($workflow, $reviewerId, $taxonomy, $sourceReviewInput, $approvalInput): string {
    $version = $workflow->version($proposalId);
    $workflow->assign($proposalId, $reviewerId, $version);
    $workflow->startReview($proposalId, $reviewerId, $version);
    if ($withSourceReview) $workflow->reviewSource($proposalId, 'purdue-owl-writing', $reviewerId, ['proposal_version' => $version] + $sourceReviewInput);
    $workflow->reconcileTaxonomy($proposalId, $reviewerId, $version, $taxonomy, 'Synthetic test mapping uses an existing valid LET English concept and depth.');
    $workflow->reconcileStudyLink($proposalId, $reviewerId, $version, [
        'status' => 'APPROVED_EXACT_LINK', 'material_id' => 'let-english-reading-language-and-composition', 'section_id' => 'reading-claim-and-evidence',
    ], 'Synthetic test mapping uses the existing published evidence section in this board and subject.');
    $workflow->decide($proposalId, $reviewerId, ['proposal_version' => $version] + $approvalInput);
    return $version;
};

// A missing independent source decision blocks import. Adding a source decision after approval invalidates approval.
$rollbackId = 'b714-test-rollback';
$rollbackVersion = $prepare($rollbackId, false);
$missingSourcePreflight = $workflow->importPreflight($rollbackId, $importerId);
$assert(!$missingSourcePreflight['eligible'] && in_array('SOURCE_REVIEW_REQUIRED:purdue-owl-writing', $missingSourcePreflight['blocked'], true), 'Import passed without an independent source review.');
$workflow->reviewSource($rollbackId, $sourceId, $reviewerId, ['proposal_version' => $rollbackVersion] + $sourceReviewInput);
$assert($workflow->record($rollbackId)['state'] === 'PENDING_REVIEW', 'Changing source evidence after approval did not invalidate the approval state.');
$rollbackVersion = $workflow->version($rollbackId);
$workflow->assign($rollbackId, $reviewerId, $rollbackVersion);
$workflow->startReview($rollbackId, $reviewerId, $rollbackVersion);
$workflow->decide($rollbackId, $reviewerId, ['proposal_version' => $rollbackVersion] + $approvalInput);
$rollbackPreflight = $workflow->importPreflight($rollbackId, $importerId);
$assert($rollbackPreflight['eligible'] === true, 'Synthetic rollback proposal did not pass the complete isolated preflight: ' . implode(',', $rollbackPreflight['blocked']));
$assert(!$workflow->importPreflight($rollbackId, $reviewerId)['eligible'], 'The academic approver was allowed to perform the separately authorized import.');
$samePersonPreflight = $workflow->importPreflight($rollbackId, $reviewerId);
$assert(in_array('IMPORT_AUTHORIZATION_REQUIRED', $samePersonPreflight['blocked'], true), 'An academic reviewer without import capability passed import authorization.');
$faultingWorkflow = new \App\Services\Question\AcademicReviewWorkflowService(
    $storage, $proposals, $authorization, $fixture,
    static function (string $stage): void { if ($stage === 'after_study_link') throw new RuntimeException('INJECTED_TEST_FAILURE'); }
);
$expectFailure(fn() => $faultingWorkflow->import($rollbackId, $importerId, $rollbackVersion, $rollbackPreflight['preview_token'], true), 'INJECTED_TEST_FAILURE', 'import rollback');
$rollbackQuestionId = 'synthetic-b714-rollback';
$rollbackLinkId = 'link-b714-' . substr(hash('sha256', $rollbackId), 0, 24);
$assert($storage->find('questions', $rollbackQuestionId) === null && $storage->find('study-question-links', $rollbackLinkId) === null, 'A failed staged import left a canonical question or study link behind.');
$rollbackJournal = $storage->find('academic-import-journal', 'academic-import-' . hash('sha256', $rollbackId));
$assert(($rollbackJournal['state'] ?? '') === 'ROLLED_BACK', 'A failed staged import did not retain a rollback journal event.');

// The exact study link may be ready before learner visibility, but a draft canonical item must remain unselectable.
$activationPreflight = $workflow->importPreflight($rollbackId, $importerId);
$visibilityFaultWorkflow = new \App\Services\Question\AcademicReviewWorkflowService(
    $storage, $proposals, $authorization, $fixture,
    static function (string $stage) use ($storage, $assert): void {
        if ($stage !== 'after_study_link_activation_before_canonical') return;
        $draft = $storage->find('questions', 'synthetic-b714-rollback');
        $linkId = 'link-b714-' . substr(hash('sha256', 'b714-test-rollback'), 0, 24);
        $link = $storage->find('study-question-links', $linkId);
        $assert(($draft['status'] ?? '') === 'draft', 'Canonical question became learner-visible before the linked section was ready.');
        $assert(($link['status'] ?? '') === 'APPROVED', 'Import did not prepare its reviewed exact link before canonical activation.');
        $selection = new \SelectionRequest('english', 'english-reading-comprehension', [], 1, exam: 'let');
        $visible = \QuestionPoolFilter::filter(
            [$draft], $selection, \App\Services\Question\QuestionEligibilityService::index()
        );
        $assert($visible === [], 'A staged draft question entered the production learner question pool.');
        throw new RuntimeException('INJECTED_TEST_FAILURE_AFTER_LINK_ACTIVATION');
    }
);
$expectFailure(
    fn() => $visibilityFaultWorkflow->import($rollbackId, $importerId, $rollbackVersion, $activationPreflight['preview_token'], true),
    'INJECTED_TEST_FAILURE_AFTER_LINK_ACTIVATION',
    'learner visibility boundary rollback'
);
$assert($storage->find('questions', 'synthetic-b714-rollback') === null
    && $storage->find('study-question-links', 'link-b714-' . substr(hash('sha256', $rollbackId), 0, 24)) === null,
    'Failure after study-link activation left a partial import published.');

// Recovery repairs a simulated process interruption without deleting unrelated records.
$recoveryQuestionId = 'synthetic-b714-recovery';
$recoveryLinkId = 'synthetic-link-b714-recovery';
$recoveryOperationId = 'academic-import-test-interrupted';
$storage->create('questions', ['id' => $recoveryQuestionId, 'status' => 'draft', 'academic_import' => ['state' => 'STAGED', 'operation_id' => $recoveryOperationId]]);
$recoveryLink = ['id' => $recoveryLinkId, 'question_id' => $recoveryQuestionId, 'material_id' => 'let-english-reading-language-and-composition', 'section_id' => 'reading-claim-and-evidence', 'status' => 'STAGED', 'import_operation_id' => $recoveryOperationId];
$storage->create('study-question-links', $recoveryLink);
$storage->create('academic-import-journal', ['id' => $recoveryOperationId, 'question_id' => $recoveryQuestionId, 'study_link_id' => $recoveryLinkId, 'study_link' => $recoveryLink, 'state' => 'PREPARING']);
$workflow->importPreflight($revisionId, $importerId);
$assert(($storage->find('academic-import-journal', $recoveryOperationId)['state'] ?? '') === 'PREPARING'
    && ($storage->find('questions', $recoveryQuestionId)['status'] ?? '') === 'draft', 'A read-only preflight mutated interrupted-import recovery state.');
$recovered = $workflow->recoverIncompleteImports();
$assert(in_array($recoveryOperationId . ':ROLLED_BACK', $recovered, true), 'Interrupted import recovery did not roll back the staged state.');
$assert($storage->find('questions', $recoveryQuestionId) === null && $storage->find('study-question-links', $recoveryLinkId) === null, 'Interrupted recovery left staged data visible.');

// Simulate process termination after canonical activation but before journal commit, including a missing link write.
$committedRecoveryQuestionId = 'synthetic-b714-committed-recovery';
$committedRecoveryLinkId = 'synthetic-link-b714-committed-recovery';
$committedRecoveryOperationId = 'academic-import-test-active-interrupted';
$committedRecoveryLink = [
    'id' => $committedRecoveryLinkId, 'question_id' => $committedRecoveryQuestionId,
    'material_id' => 'let-english-reading-language-and-composition',
    'section_id' => 'reading-claim-and-evidence', 'status' => 'STAGED',
    'import_operation_id' => $committedRecoveryOperationId,
];
$storage->create('questions', [
    'id' => $committedRecoveryQuestionId, 'status' => 'active',
    'academic_import' => [
        'state' => 'IMPORTED', 'operation_id' => $committedRecoveryOperationId,
        'audit_id' => 'synthetic-b714-recovery-audit',
    ],
]);
$storage->create('academic-import-journal', [
    'id' => $committedRecoveryOperationId, 'question_id' => $committedRecoveryQuestionId,
    'study_link_id' => $committedRecoveryLinkId, 'study_link' => $committedRecoveryLink,
    'state' => 'PREPARING',
]);
$committedRecovery = $workflow->recoverIncompleteImports();
$recoveredJournal = $storage->find('academic-import-journal', $committedRecoveryOperationId);
$recoveredLink = $storage->find('study-question-links', $committedRecoveryLinkId);
$assert(in_array($committedRecoveryOperationId . ':COMMITTED', $committedRecovery, true)
    && ($recoveredJournal['state'] ?? '') === 'COMMITTED'
    && ($recoveredLink['status'] ?? '') === 'APPROVED',
    'Recovery did not finish an active audited import with a partially written study link.');
$secondRecovery = $workflow->recoverIncompleteImports();
$assert($secondRecovery === []
    && ($storage->find('academic-import-journal', $committedRecoveryOperationId)['state'] ?? '') === 'COMMITTED'
    && ($storage->find('study-question-links', $committedRecoveryLinkId)['status'] ?? '') === 'APPROVED',
    'Repeated recovery changed an already committed import or duplicated its study link.');

// A missing taxonomy/approval, source-registry change, ID collision, and exact duplicate are independent blockers.
$unreviewed = $workflow->importPreflight('b714-test-concurrent', $importerId);
$assert(in_array('ACADEMIC_APPROVAL_REQUIRED', $unreviewed['blocked'], true)
    && in_array('VALID_REVIEWED_TAXONOMY_REQUIRED', $unreviewed['blocked'], true), 'A proposal without approval and valid taxonomy was import-ready.');
$collisionPreflight = $workflow->importPreflight('b714-test-collision', $importerId);
$assert(in_array('CANONICAL_ID_COLLISION', $collisionPreflight['blocked'], true), 'A canonical ID collision was not detected.');
$canonicalQuestion = $storage->find('questions', '1');
$duplicateProposal = $proposalFactory('b714-test-duplicate', 'synthetic-b714-duplicate', (string) ($canonicalQuestion['question'] ?? ''));
$duplicateWorkflow = new \App\Services\Question\AcademicReviewWorkflowService($storage, array_merge($proposals, [$duplicateProposal]), $authorization, $fixture);
$duplicateVersion = $duplicateWorkflow->version('b714-test-duplicate');
$duplicateWorkflow->assign('b714-test-duplicate', $reviewerId, $duplicateVersion);
$duplicateWorkflow->startReview('b714-test-duplicate', $reviewerId, $duplicateVersion);
$duplicateWorkflow->reviewSource('b714-test-duplicate', $sourceId, $reviewerId, ['proposal_version' => $duplicateVersion] + $sourceReviewInput);
$duplicateWorkflow->reconcileTaxonomy('b714-test-duplicate', $reviewerId, $duplicateVersion, $taxonomy, 'Synthetic duplicate test uses an existing valid taxonomy and depth mapping.');
$duplicateWorkflow->reconcileStudyLink('b714-test-duplicate', $reviewerId, $duplicateVersion, [
    'status' => 'APPROVED_EXACT_LINK', 'material_id' => 'let-english-reading-language-and-composition', 'section_id' => 'reading-claim-and-evidence',
], 'Synthetic duplicate test links the exact published claim-and-evidence section.');
$duplicateWorkflow->decide('b714-test-duplicate', $reviewerId, ['proposal_version' => $duplicateVersion] + $approvalInput);
$duplicatePreflight = $duplicateWorkflow->importPreflight('b714-test-duplicate', $importerId);
$assert(in_array('EXACT_DUPLICATE_QUESTION', $duplicatePreflight['blocked'], true), 'An exact canonical stem duplicate was not blocked.');

// Controlled synthetic approval and separately authorized import, tested concurrently.
$concurrentId = 'b714-test-concurrent';
$concurrentVersion = $prepare($concurrentId, true);
$storage->replace('academic-reviewer-authorizations', array_values(array_filter(
    $grants,
    static fn(array $grant): bool => ($grant['id'] ?? '') !== 'grant-test-reviewer'
)));
$revokedApprovalPreflight = $workflow->importPreflight($concurrentId, $importerId);
$assert(in_array('APPROVAL_REVIEWER_AUTHORIZATION_INVALID', $revokedApprovalPreflight['blocked'], true)
    && in_array('SOURCE_REVIEWER_AUTHORIZATION_INVALID:' . $sourceId, $revokedApprovalPreflight['blocked'], true), 'Import remained eligible after its academic and source reviewers lost authorization.');
$storage->replace('academic-reviewer-authorizations', $grants);
$sourceRow = $storage->find('sources', $sourceId);
$sourceRows = $storage->all('sources');
$changedSourceRows = array_map(static function (array $row) use ($sourceId): array {
    if (($row['id'] ?? '') === $sourceId) $row['notes'] = 'TEST-ONLY change used to verify registry snapshot invalidation.';
    return $row;
}, $sourceRows);
$storage->replace('sources', $changedSourceRows);
$staleSourcePreflight = $workflow->importPreflight($concurrentId, $importerId);
$assert(in_array('SOURCE_REGISTRY_CHANGED:' . $sourceId, $staleSourcePreflight['blocked'], true), 'Import did not detect a changed source registry record.');
$storage->replace('sources', $sourceRows);
$concurrentPreflight = $workflow->importPreflight($concurrentId, $importerId);
$assert($concurrentPreflight['eligible'] === true, 'Concurrent test proposal did not pass import preflight: ' . implode(',', $concurrentPreflight['blocked']));
$assert(($concurrentPreflight['question']['id'] ?? '') === 'synthetic-b714-concurrent'
    && ($concurrentPreflight['study_link']['section_id'] ?? '') === 'reading-claim-and-evidence', 'Import preview omitted the exact canonical or study-link change.');
$assert(count($concurrentPreflight['source_review_ids']) === 1, 'Import preview did not bind the reviewed source list.');
$assert(!$workflow->importPreflight($concurrentId, 'account-unprivileged')['eligible'], 'An unauthorized importer passed the import gate.');
$expectFailure(fn() => $workflow->import($concurrentId, $importerId, $concurrentVersion, $concurrentPreflight['preview_token'], false), 'IMPORT_CONFIRMATION_REQUIRED', 'missing import confirmation');

$parallelResults = [$fixture . '/concurrent-a.json', $fixture . '/concurrent-b.json'];
if (function_exists('pcntl_fork') && function_exists('pcntl_waitpid')) {
    $children = [];
    foreach ($parallelResults as $resultPath) {
        $pid = pcntl_fork();
        if ($pid === -1) throw new RuntimeException('Unable to fork isolated concurrent import test.');
        if ($pid === 0) {
            try {
                $childStorage = new \App\Storage\JsonStorage($fixture);
                $childPdo = new PDO('sqlite:' . $fixture . '/runtime/batch714-review.sqlite', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                $childSqlite = new \App\Storage\SqliteStorage($childPdo);
                $childStorage = new \App\Storage\StorageRouter($childStorage, [
                    'attempts' => $childSqlite, 'weakness' => $childSqlite,
                    'flashcard_state' => $childSqlite, 'question-statistics' => $childSqlite,
                ]);
                $childAuth = new \App\Services\Question\AcademicReviewerAuthorizationService($childStorage);
                \App\Core\App::container()->bind(\App\Contracts\StorageInterface::class, static fn() => $childStorage);
                $childQuestionRepository = new \App\Repositories\QuestionRepository($childStorage);
                \App\Core\App::container()->bind(\App\Repositories\QuestionRepository::class, static fn() => $childQuestionRepository);
                $childWorkflow = new \App\Services\Question\AcademicReviewWorkflowService($childStorage, $proposals, $childAuth, $fixture);
                $childWorkflow->import($concurrentId, $importerId, $concurrentVersion, $concurrentPreflight['preview_token'], true);
                file_put_contents($resultPath, 'IMPORTED', LOCK_EX);
            } catch (Throwable $exception) {
                file_put_contents($resultPath, 'BLOCKED:' . $exception->getMessage(), LOCK_EX);
            }
            $cleanupFixtureOnShutdown = false;
            exit(0);
        }
        $children[] = $pid;
    }
    foreach ($children as $pid) pcntl_waitpid($pid, $status);
    $parallelOutcomes = array_map(static fn(string $path): string => (string) file_get_contents($path), $parallelResults);
    foreach ($parallelResults as $path) @unlink($path);
    $assert(count(array_filter($parallelOutcomes, static fn(string $outcome): bool => $outcome === 'IMPORTED')) === 1, 'Concurrent identical import submissions did not commit exactly once: ' . implode(' | ', $parallelOutcomes));
    $assert(count(array_filter($parallelOutcomes, static fn(string $outcome): bool => str_starts_with($outcome, 'BLOCKED:'))) === 1, 'Concurrent repeat submission was not blocked after the first import.');
} else {
    $expectFailure(static function () use ($workflow, $concurrentId, $importerId, $concurrentVersion, $concurrentPreflight): void {
        $workflow->import($concurrentId, $importerId, $concurrentVersion, $concurrentPreflight['preview_token'], true);
    }, 'IMPORT_PREFLIGHT_BLOCKED', 'idempotent duplicate import');
    $parallelOutcomes = ['CONCURRENCY_DEPENDENCY_MISSING'];
}

$canonical = $storage->find('questions', 'synthetic-b714-concurrent');
$assert(is_array($canonical) && ($canonical['status'] ?? '') === 'active', 'Synthetic question was not published through the canonical question collection.');
$assert(($canonical['academic_import']['state'] ?? '') === 'IMPORTED'
    && ($canonical['academic_import']['proposal_version'] ?? '') === $concurrentVersion
    && ($canonical['academic_import']['academic_reviewer_id'] ?? '') === $reviewerId
    && ($canonical['academic_import']['imported_by'] ?? '') === $importerId, 'Canonical import audit omitted its reviewer, importer, or version binding.');
$assert(($storage->find('academic-import-journal', 'academic-import-' . hash('sha256', $concurrentId))['state'] ?? '') === 'COMMITTED', 'Successful import journal was not committed.');
$assert(($storage->find('study-question-links', 'link-b714-' . substr(hash('sha256', $concurrentId), 0, 24))['status'] ?? '') === 'APPROVED', 'Exact study link was not activated after the canonical commit.');
$repeatPreflight = $workflow->importPreflight($concurrentId, $importerId);
$assert(!$repeatPreflight['eligible'] && in_array('ALREADY_IMPORTED', $repeatPreflight['blocked'], true), 'Repeated import was not idempotently blocked.');
$workflow->record($concurrentId); // verifies the event hash chain after the import.

// Exercise learner-facing production services using only the isolated synthetic question.
$questionRepository = new \App\Repositories\QuestionRepository($storage);
\App\Core\App::container()->bind(\App\Repositories\QuestionRepository::class, static fn() => $questionRepository);
$loaded = $questionRepository->find('synthetic-b714-concurrent');
$assert(is_array($loaded) && ($loaded['question'] ?? '') === $canonical['question'], 'Question Repository did not expose the imported canonical question.');
$eligibility = \App\Services\Question\QuestionEligibilityService::index();
$assert(\App\Services\Question\QuestionEligibilityService::forExam($loaded, 'let', $eligibility) !== null, 'Canonical LET taxonomy did not provide same-board eligibility.');
$assert(\App\Services\Question\QuestionEligibilityService::forExam($loaded, 'nursing', $eligibility) === null, 'Imported question leaked into an unrelated examination board.');
$selection = new \SelectionRequest('english', 'english-reading-comprehension', [], 1, exam: 'let');
$selected = \QuestionPoolFilter::filter([$loaded], $selection, $eligibility);
$assert(count($selected) === 1 && (string) $selected[0]['id'] === 'synthetic-b714-concurrent', 'Production quiz selection did not select the imported synthetic item in its approved board pool.');
$score = \QuestionScoreEvaluator::evaluate($loaded, (string) $loaded['answer']);
$assert($score['correct'] === true && $score['correctAnswer'] === $loaded['answer'], 'Standard scorer did not honor the reviewed canonical answer.');

$_SESSION = ['learner_identity' => 'learner-' . str_repeat('c', 32)];
$attempt = (new \App\Services\AttemptService($attemptRepository))->save([
    'id' => 'attempt-b714-synthetic', 'completed' => true, 'completed_at' => gmdate(DATE_ATOM),
    'mode' => 'practice', 'board' => 'let', 'subject' => 'english', 'score' => 0, 'total' => 1,
    'review' => [[
        'question' => $loaded, 'userAnswer' => 'The evidence is irrelevant.',
        'correctAnswer' => (string) $loaded['answer'], 'answered' => true, 'correct' => false,
    ]],
]);
$assert(($attempt['user_id'] ?? '') === $_SESSION['learner_identity'], 'Result persistence did not bind the attempt to the isolated learner.');
$assert($sqliteStorage->find('attempts', 'attempt-b714-synthetic') !== null, 'The SQLite learner-storage route did not persist the synthetic attempt.');
$recent = \App\Services\Learning\LearningHistoryService::recent(5);
$assert(count($recent) === 1 && ($recent[0]['id'] ?? '') === 'attempt-b714-synthetic', 'Learning history did not include the persisted synthetic result.');
$mistakes = \App\Services\Learning\MistakeNotebookService::summaries($recent);
$assert(count($mistakes) === 1 && (string) $mistakes[0]['question_id'] === 'synthetic-b714-concurrent', 'Mistake Notebook did not retain the learner result for the imported item.');
$studyReferences = \App\Services\Study\StudyLibraryService::forQuestion('synthetic-b714-concurrent', 'let', 'english');
$assert(count($studyReferences) === 1 && ($studyReferences[0]['section_id'] ?? '') === 'reading-claim-and-evidence', 'Study navigation did not resolve the approved exact section.');
$noteMatch = (new \App\Services\Learning\NotesQuestionMatcherService())->match('let', $loaded['question'] . "\n" . $loaded['explanation']);
$noteMatches = array_map(static fn(array $item): string => (string) ($item['question_id'] ?? ''), $noteMatch['candidates']);
$assert(in_array('synthetic-b714-concurrent', $noteMatches, true), 'My Notes matching did not reach the canonical imported question through the production pool gate.');

// Authenticated HTTP workbench, CSRF protection, escaping, and client identity spoofing.
require_once dirname(__DIR__) . '/Doctor/Project/BoardPrep/Simulation/HttpSimulator.php';
$http = new \Tools\Doctor\Project\BoardPrep\Simulation\HttpSimulator($root . '/public/index.php');
$accountRepository = new \App\Services\Accounts\AccountRepository($fixture . '/private/learner-accounts');
$httpAccountId = 'account-' . bin2hex(random_bytes(16));
$httpLearnerId = 'account-learner-' . bin2hex(random_bytes(16));
$assert($accountRepository->create([
    'account_id' => $httpAccountId, 'learner_id' => $httpLearnerId, 'username' => 'b714-test-reviewer-' . bin2hex(random_bytes(3)),
    'password_hash' => password_hash('test-only-password', PASSWORD_DEFAULT), 'status' => 'active',
    'auth_version' => 1, 'created_at' => gmdate(DATE_ATOM), 'updated_at' => gmdate(DATE_ATOM),
]), 'Isolated HTTP reviewer account could not be created.');
$httpGrant = $grant('grant-http-workbench', $httpAccountId, $reviewCapabilities);
$httpSubjectId = 'early-childhood-education';
$httpGrant['scopes'][0]['subject_id'] = $httpSubjectId;
$storage->create('academic-reviewer-authorizations', $httpGrant);
$sessionId = 'b714http' . bin2hex(random_bytes(8));
$sessionPath = $http->sessionPath();
ini_set('session.use_cookies', '0');
ini_set('session.use_strict_mode', '0');
session_save_path($sessionPath);
session_id($sessionId);
session_start();
$_SESSION = [
    'boardprep_account_auth' => ['account_id' => $httpAccountId, 'learner_id' => $httpLearnerId, 'auth_version' => 1, 'started_at' => time(), 'last_activity' => time()],
    'learner_identity' => $httpLearnerId,
    '_csrf_token' => bin2hex(random_bytes(32)),
];
$csrf = $_SESSION['_csrf_token'];
session_write_close();
$cookies = ['PHPSESSID' => $sessionId];
$anonymous = $http->request('GET', '/developer/academic-review', [], [], [], ['PHPSESSID' => 'b714anonymous']);
$assert($anonymous['status'] === 401, 'Anonymous academic review workbench access was not denied.');
$httpQueue = $http->request('GET', '/developer/academic-review', ['board' => 'let', 'subject' => $httpSubjectId], [], [], $cookies);
$assert($httpQueue['status'] === 200 && str_contains($httpQueue['output'], 'Academic review') && str_contains($httpQueue['output'], 'Page 1 of'), 'Authenticated reviewer could not open the bounded proposal workbench (HTTP ' . $httpQueue['status'] . '): ' . substr(strip_tags((string) $httpQueue['output']), 0, 500));
$pilotWorkflowBeforeHttp = $storage->all('academic-proposal-workflow');
$pilotHttpQueue = $http->request('GET', '/developer/academic-review', [
    'board' => 'let', 'subject' => $httpSubjectId, 'pilot' => '1', 'page_num' => '1',
], [], [], $cookies);
preg_match_all('/data-proposal-id="(batch695-[0-9]+)"/', (string) $pilotHttpQueue['output'], $pilotQueueMatches);
$pilotVisibleIds = $pilotQueueMatches[1] ?? [];
sort($pilotVisibleIds, SORT_STRING);
$assert($pilotHttpQueue['status'] === 200 && $pilotVisibleIds === ['batch695-9200','batch695-9204','batch695-9210','batch695-9228'], 'Scoped pilot filter did not return exactly the four authorized ECE pilot proposals.');
$assert(str_contains($pilotHttpQueue['output'], '20-item academic pilot')
    && str_contains($pilotHttpQueue['output'], 'Source dossier:')
    && str_contains($pilotHttpQueue['output'], 'Page 1 of 1'), 'Pilot queue omitted progress, evidence readiness, or bounded pagination cues.');
$assert($storage->all('academic-proposal-workflow') === $pilotWorkflowBeforeHttp, 'Reading the pilot queue wrote review decisions or assignments.');
$pilotDetail = $http->request('GET', '/developer/academic-review/proposal', ['id' => 'batch695-9200'], [], [], $cookies);
$assert($pilotDetail['status'] === 200
    && str_contains($pilotDetail['output'], 'Machine-prepared dossier')
    && str_contains($pilotDetail['output'], 'independent review pending')
    && str_contains($pilotDetail['output'], 'What the evidence establishes')
    && str_contains($pilotDetail['output'], 'Proposed answer:')
    && str_contains($pilotDetail['output'], 'Next eligible action:'), 'Pilot inspector did not present the version-bound evidence and next-review action.');
$httpProposal = null;
foreach (\App\Services\Question\AcademicReviewProposalService::all() as $candidate) {
    if (($candidate['board_id'] ?? '') === 'let' && ($candidate['subject_id'] ?? '') === $httpSubjectId) { $httpProposal = $candidate; break; }
}
$assert(is_array($httpProposal), 'No real, still-pending B695 proposal was available to test the read-only queue.');
$proposalVersion = (new \App\Services\Question\AcademicReviewWorkflowService())->version((string) $httpProposal['id']);
$workflowBeforeHttp = $storage->find('academic-proposal-workflow', (string) $httpProposal['id']);
$csrfDenied = $http->request('POST', '/developer/academic-review/assign', [], [
    'proposal_id' => $httpProposal['id'], 'proposal_version' => $proposalVersion, 'csrf_token' => 'invalid', 'reviewer_id' => 'forged-reviewer-id',
], [], $cookies);
$assert($csrfDenied['status'] === 403, 'CSRF-protected review mutation accepted an invalid token.');
$assert($storage->find('academic-proposal-workflow', (string) $httpProposal['id']) === $workflowBeforeHttp, 'CSRF rejection mutated the proposal workflow.');
$detail = $http->request('GET', '/developer/academic-review/proposal', ['id' => $httpProposal['id']], [], [], $cookies);
$assert($detail['status'] === 200 && str_contains($detail['output'], 'Proposed question') && str_contains($detail['output'], 'Source evidence and independent review'), 'Reviewer could not open full proposal and evidence detail.');
preg_match('/name="proposal_version" value="([a-f0-9]{64})"/', $detail['output'], $versionMatch);
$assert(($versionMatch[1] ?? '') === $proposalVersion, 'The detail view did not bind mutations to the visible proposal version.');
$httpAssign = $http->request('POST', '/developer/academic-review/assign', [], [
    'proposal_id' => $httpProposal['id'], 'proposal_version' => $proposalVersion, 'csrf_token' => $csrf,
    'reviewer_id' => 'forged-reviewer-id', 'role' => 'ADMIN',
], [], $cookies);
$assert($httpAssign['status'] === 303, 'Authenticated scoped reviewer could not self-assign a proposal.');
$assignedRealProposal = $storage->find('academic-proposal-workflow', (string) $httpProposal['id']);
$assert(($assignedRealProposal['assigned_reviewer_id'] ?? '') === $httpAccountId
    && ($assignedRealProposal['decisions'][0]['reviewer_id'] ?? '') === $httpAccountId
    && ($assignedRealProposal['decisions'][0]['reviewer_id'] ?? '') !== 'forged-reviewer-id', 'Client-supplied reviewer ID or role spoofed the authenticated audit identity.');
$assert(($assignedRealProposal['state'] ?? '') === 'ASSIGNED', 'HTTP assignment did not preserve the pending academic decision state.');

ob_start();
\App\Core\View::render('developer/academic-review/proposal', [
    'proposal' => $proposalFactory('b714-html-test', 'synthetic-b714-html', '<script>alert(1)</script> Which evidence supports this conclusion?'),
    'originalProposal' => $proposalFactory('b714-html-test', 'synthetic-b714-html', '<img src=x onerror=alert(1)>'),
    'workflow' => ['state' => 'PENDING_REVIEW', 'decisions' => [], 'source_reviews' => [], 'source_review_history' => []],
    'proposalVersion' => str_repeat('a', 64), 'capabilities' => [], 'boardName' => '<script>', 'subjectName' => '<img>', 'csrfToken' => 'token',
]);
$escapedView = (string) ob_get_clean();
$assert(!str_contains($escapedView, '<script>alert(1)</script>') && str_contains($escapedView, '&lt;script&gt;alert(1)&lt;/script&gt;'), 'Proposal HTML failed to escape untrusted content.');

// Learner-facing question review remains private to the authenticated learner.
$attemptRepo = new \App\Repositories\AttemptRepository($storage);
$assert($attemptRepo->findForLearner('attempt-b714-synthetic', 'learner-' . str_repeat('d', 32)) === null, 'Another learner could access the isolated result.');
$assert(hash_file('sha256', $root . '/storage/questions.json') === $protectedQuestionBankHash, 'The production canonical question bank changed during isolated tests.');
$assert(hash_file('sha256', $root . '/storage/question-eligibility.json') === $protectedEligibilityHash, 'The production eligibility mapping changed during isolated tests.');
$assert(count(json_decode((string) file_get_contents($root . '/storage/questions.json'), true, 512, JSON_THROW_ON_ERROR)) === 9123, 'Production canonical question count changed during isolated tests.');

echo "[PASS] scoped reviewer authorization, versioned decisions, revision invalidation, independent source/taxonomy gates, staged rollback/recovery, concurrent idempotent import, authenticated HTTP/CSRF/identity controls, escaping, canonical learner eligibility, scoring, result persistence, history, notes, mistakes, and exact study navigation.\n";
