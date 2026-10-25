<?php

declare(strict_types=1);

require_once __DIR__ . '/Support/StaticStorageFixture.php';

$root = dirname(__DIR__, 2);
$fixture = StaticStorageFixture::create($root, 'batch715-academic-pilot');
$removeFixture = true;
register_shutdown_function(static function () use ($fixture, &$removeFixture): void {
    if (!$removeFixture) return;
    $real = realpath($fixture);
    $temporary = realpath(sys_get_temp_dir());
    if (!is_string($real) || !is_string($temporary) || !str_starts_with($real, $temporary . DIRECTORY_SEPARATOR)
        || !str_starts_with(basename($real), 'boardprep-batch715-academic-pilot-')) return;
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
    if (!$condition) throw new RuntimeException('Batch 715 pilot test failed: ' . $message);
};

$protectedQuestions = hash_file('sha256', $root . '/storage/questions.json');
$protectedEligibility = hash_file('sha256', $root . '/storage/question-eligibility.json');
$cohort = \App\Services\Question\Batch715AcademicPilotService::cohort();
$readiness = \App\Services\Question\Batch715AcademicPilotService::readiness();
$items = (array) ($cohort['items'] ?? []);
$evidenceById = [];
foreach ((array) ($readiness['items'] ?? []) as $evidence) $evidenceById[(string) ($evidence['question_id'] ?? '')] = $evidence;
$assert(count($items) === 20 && count($evidenceById) === 20, 'Pilot cohort and source dossiers must each contain exactly 20 entries.');
$proposalWorkflow = new \App\Services\Question\AcademicReviewWorkflowService(
    \App\Core\App::storage(), \App\Services\Question\AcademicReviewProposalService::all(),
    new \App\Services\Question\AcademicReviewerAuthorizationService(\App\Core\App::storage()), $fixture
);
$boards = $subjects = [];
$exactSections = 0;
foreach ($items as $item) {
    $id = (string) ($item['question_id'] ?? '');
    $boards[(string) ($item['board']['id'] ?? '')] = true;
    $subjects[(string) ($item['subject']['id'] ?? '')] = true;
    $assert(\App\Services\Question\Batch715AcademicPilotService::isSelected($id), 'Pilot lookup did not include ' . $id . '.');
    $assert(\App\Core\App::storage()->find('questions', $id) === null, 'Real B695 proposal is present in canonical storage: ' . $id);
    $assert(($item['review_state'] ?? '') === 'PENDING_REVIEW'
        && ($item['production_status'] ?? '') === 'NONCANONICAL_NOT_IN_LEARNER_POOLS', 'Real pilot proposal was promoted: ' . $id);
    $assert(hash_equals((string) $item['question_version'], $proposalWorkflow->version((string) $item['proposal_id'])), 'Dossier version is stale for ' . $id);
    $dossier = $evidenceById[$id] ?? [];
    $assert(($dossier['academic_approval'] ?? '') === 'NOT_APPROVED', 'Dossier implies an approval for ' . $id);
    foreach ((array) ($dossier['sources'] ?? []) as $source) {
        foreach (['url_reachable','source_identity_confirmed','content_examined'] as $field) {
            $assert(array_key_exists($field, $source), 'Dossier collapses or omits evidence state ' . $field . ' for ' . $id);
        }
        $assert(($source['expert_source_review'] ?? '') === 'PENDING_QUALIFIED_REVIEWER', 'Dossier claims expert review for ' . $id);
    }
    $study = (array) ($item['study_relationships'] ?? []);
    if (($study['status'] ?? '') === 'EXACT_SECTION_MATCH') {
        $exactSections++;
        $material = \App\Services\Study\StudyLibraryService::find((string) ($study['material_id'] ?? ''));
        $lesson = is_array($material['lesson'] ?? null) ? $material['lesson'] : [];
        $sectionIds = array_map(static fn(array $section): string => (string) ($section['id'] ?? ''), array_values(array_filter((array) ($lesson['sections'] ?? []), 'is_array')));
        $assert(in_array((string) ($study['section_id'] ?? ''), $sectionIds, true), 'Study candidate is not an exact published section for ' . $id);
        $assert(\App\Services\Study\StudyLibraryService::forQuestion($id, (string) $item['board']['id'], (string) $item['subject']['id']) === [], 'Study candidate became an active link before review for ' . $id);
    }
}
$assert(count($boards) > 1 && count($subjects) >= 5 && $exactSections === 3, 'Pilot cohort diversity or exact-section candidates differ from the selection record.');
$assert(hash_file('sha256', $root . '/storage/questions.json') === $protectedQuestions
    && hash_file('sha256', $root . '/storage/question-eligibility.json') === $protectedEligibility, 'Pilot package inspection changed canonical questions or eligibility.');

// Exercise the real authenticated HTTP workbench using one synthetic grant and isolated test storage.
require_once dirname(__DIR__) . '/Doctor/Project/BoardPrep/Simulation/HttpSimulator.php';
$storage = \App\Core\App::storage();
$accountId = 'account-' . bin2hex(random_bytes(16));
$learnerId = 'account-learner-' . bin2hex(random_bytes(16));
$accountRepository = new \App\Services\Accounts\AccountRepository($fixture . '/private/learner-accounts');
$assert($accountRepository->create([
    'account_id' => $accountId, 'learner_id' => $learnerId,
    'username' => 'b715-pilot-test-' . bin2hex(random_bytes(4)),
    'password_hash' => password_hash('synthetic-pilot-only-password', PASSWORD_DEFAULT),
    'status' => 'active', 'auth_version' => 1, 'created_at' => gmdate(DATE_ATOM), 'updated_at' => gmdate(DATE_ATOM),
]), 'Isolated synthetic reviewer could not be provisioned.');
$storage->create('academic-reviewer-authorizations', [
    'id' => 'b715-test-only-review-grant-' . bin2hex(random_bytes(4)),
    'account_id' => $accountId, 'authorization_status' => 'ACTIVE',
    'qualification_type' => 'SUBJECT_EXPERT', 'qualification_reference' => 'synthetic-test-fixture-only',
    'authorized_by' => 'synthetic-test-owner', 'authorized_at' => gmdate(DATE_ATOM),
    'expires_at' => '2099-12-31T23:59:59+00:00',
    'scopes' => [[
        'board_id' => 'let', 'subject_id' => 'early-childhood-education',
        'capabilities' => ['REVIEW','SOURCE_REVIEW','TAXONOMY_RECONCILE','STUDY_LINK'],
    ]],
]);
$http = new \Tools\Doctor\Project\BoardPrep\Simulation\HttpSimulator($root . '/public/index.php');
$sessionId = 'b715pilot' . bin2hex(random_bytes(8));
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
$httpQueue = $http->request('GET', '/developer/academic-review', [
    'board' => 'let', 'subject' => 'early-childhood-education', 'pilot' => '1',
], [], [], ['PHPSESSID' => $sessionId]);
if ($httpQueue['status'] !== 200) {
    throw new RuntimeException('Batch 715 pilot HTTP workbench failed: HTTP ' . $httpQueue['status'] . ' ' . trim((string) $httpQueue['stderr']) . ' ' . substr(strip_tags((string) $httpQueue['output']), 0, 300));
}
preg_match_all('/data-proposal-id="(batch695-[0-9]+)"/', (string) $httpQueue['output'], $matches);
$visibleIds = $matches[1] ?? [];
sort($visibleIds, SORT_STRING);
$assert($visibleIds === ['batch695-9200','batch695-9204','batch695-9210','batch695-9228'], 'Authenticated pilot queue escaped its board/subject scope or omitted selected items.');
$assert(str_contains((string) $httpQueue['output'], '20-item academic pilot')
    && str_contains((string) $httpQueue['output'], 'Source dossier:')
    && str_contains((string) $httpQueue['output'], 'Page 1 of 1'), 'Pilot queue omitted progress, evidence, or pagination UI.');
$httpDetail = $http->request('GET', '/developer/academic-review/proposal', ['id' => 'batch695-9200'], [], [], ['PHPSESSID' => $sessionId]);
if ($httpDetail['status'] !== 200) {
    throw new RuntimeException('Batch 715 pilot inspector failed: HTTP ' . $httpDetail['status'] . ' ' . trim((string) $httpDetail['stderr']) . ' ' . substr(strip_tags((string) $httpDetail['output']), 0, 300));
}
$assert(str_contains((string) $httpDetail['output'], 'Machine-prepared dossier')
    && str_contains((string) $httpDetail['output'], 'Next eligible action:')
    && str_contains((string) $httpDetail['output'], 'Proposed answer:'), 'Proposal inspector omitted evidence, proposed answer, or next action.');
$assert($storage->all('academic-proposal-workflow') === [], 'Read-only pilot browser journey wrote an assignment or academic decision.');

echo "[PASS] reproducible 20-item package, current proposal versions, noncanonical and pending states, distinct evidence statuses, published StudyLibrary exact sections, scoped pilot HTTP queue, evidence inspector, read-only state, and canonical integrity.\n";
