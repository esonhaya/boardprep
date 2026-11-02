<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

use App\Core\App;
use App\Exceptions\ExamDraftConflictException;
use App\Exceptions\ExamDraftUnavailableException;
use App\Repositories\AttemptRepository;
use App\Services\Accounts\AccountRepository;
use App\Services\Accounts\AccountRestoreService;
use App\Services\Accounts\AccountService;
use App\Services\Quiz\ExamDraftService;

function examDraftAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function examDraftExpect(string $class, callable $operation, string $message): Throwable
{
    try {
        $operation();
    } catch (Throwable $exception) {
        if ($exception instanceof $class) {
            return $exception;
        }
        throw new RuntimeException($message . ': unexpected ' . $exception::class, 0, $exception);
    }
    throw new RuntimeException($message . ': expected ' . $class . ' was not thrown.');
}

function examDraftSession(array $account): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_unset();
        session_write_close();
    }
    session_id(bin2hex(random_bytes(16)));
    if (!session_start()) {
        throw new RuntimeException('Could not start the isolated prototype session.');
    }
    if (!(new AccountService())->signIn((string) $account['username'], (string) $account['password'])) {
        throw new RuntimeException('Could not authenticate a synthetic prototype account.');
    }
}

function examDraftSyntheticQuestions(): array
{
    return [
        [
            'id' => 'synthetic-exam-q1',
            'question' => 'Synthetic stem that must never enter the durable draft.',
            'choices' => ['Choice one', 'Choice two', 'Choice three', 'Choice four'],
            'answer' => 'B',
            'explanation' => 'Synthetic key explanation that must never enter the durable draft.',
        ],
        [
            'id' => 'synthetic-exam-q2',
            'question' => 'Second synthetic stem that must never enter the durable draft.',
            'choices' => ['Choice one', 'Choice two', 'Choice three'],
            'answer' => 'C',
            'explanation' => 'Second synthetic key explanation.',
        ],
    ];
}

function examDraftInstallSession(array $record, array $questions): void
{
    $quizSession = is_array($record['quiz_session'] ?? null) ? $record['quiz_session'] : [];
    $quizSession['id'] = (string) ($record['active_quiz_session_id'] ?? '');
    $quizSession['durable_draft_id'] = (string) ($record['id'] ?? '');
    $quizSession['durable_draft_revision'] = (int) ($record['revision'] ?? 0);
    $_SESSION['quiz_session'] = $quizSession;
    $_SESSION['questions'] = $questions;
    $_SESSION['answers'] = (array) ($record['answers'] ?? []);
    $_SESSION['currentQuestion'] = (int) ($record['current_question'] ?? 0);
    $_SESSION['exam_review_flags'] = array_fill_keys((array) ($record['review_flags'] ?? []), true);
    $_SESSION['exam_answer_revision'] = (int) ($record['exam_answer_revision'] ?? 0);
}

function examDraftRemoveFixture(string $root): void
{
    if (dirname($root) !== sys_get_temp_dir()
        || !str_starts_with(basename($root), 'boardprep-exam-draft-prototype-')
        || !is_dir($root)) {
        throw new RuntimeException('Refusing to remove a path outside the synthetic exam-draft fixture.');
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $entry) {
        if ($entry->isDir() && !$entry->isLink()) {
            @rmdir($entry->getPathname());
        } else {
            @unlink($entry->getPathname());
        }
    }
    @rmdir($root);
}

$backend = strtolower(trim((string) ($argv[1] ?? '')));
if (!in_array($backend, ['json', 'sqlite'], true)) {
    fwrite(STDERR, "Usage: php ExamDraftPrototypeTest.php json|sqlite\n");
    exit(2);
}
if ($backend === 'sqlite' && !extension_loaded('pdo_sqlite')) {
    fwrite(STDERR, "SQLite prototype backend is unavailable: pdo_sqlite is not loaded.\n");
    exit(2);
}

$root = sys_get_temp_dir() . '/boardprep-exam-draft-prototype-' . bin2hex(random_bytes(12));
$storage = $root . '/storage';
$sessions = $root . '/sessions';
mkdir($storage, 0700, true);
mkdir($sessions, 0700, true);
foreach (['boards.json', 'subjects.json', 'board-subjects.json', 'questions.json', 'question-eligibility.json'] as $file) {
    $source = dirname(__DIR__, 2) . '/storage/' . $file;
    if (is_file($source) && !copy($source, $storage . '/' . $file)) {
        throw new RuntimeException('Could not copy read-only canonical test fixture data.');
    }
}
putenv('APP_ENV=testing');
putenv('APP_DEBUG=false');
putenv('BOARDPREP_ACCOUNTS_ENABLED=true');
putenv('DB_DRIVER=' . $backend);
putenv('APP_STORAGE_PATH=' . $storage);
putenv('DB_SQLITE_PATH=' . $storage . '/runtime/boardprep.sqlite');
$_ENV['APP_ENV'] = 'testing';
$_ENV['APP_DEBUG'] = 'false';
$_ENV['BOARDPREP_ACCOUNTS_ENABLED'] = 'true';
$_ENV['DB_DRIVER'] = $backend;
$_ENV['APP_STORAGE_PATH'] = $storage;
$_ENV['DB_SQLITE_PATH'] = $storage . '/runtime/boardprep.sqlite';
$_SERVER['REMOTE_ADDR'] = '192.0.2.740';
App::boot();
session_save_path($sessions);
session_id(bin2hex(random_bytes(16)));
if (!session_start()) {
    throw new RuntimeException('Could not start the isolated prototype session.');
}

$service = new ExamDraftService();
$accounts = new AccountService();
$passwordA = 'Batch740 synthetic owner A long password';
$passwordB = 'Batch740 synthetic owner B long password';
$registeredA = $accounts->register('batch740-owner-a', $passwordA);
$registeredB = $accounts->register('batch740-owner-b', $passwordB);
examDraftAssert(is_array($registeredA['account']) && is_array($registeredB['account']), 'Synthetic account creation failed.');
$accountA = $registeredA['account'] + ['password' => $passwordA];
$accountB = $registeredB['account'] + ['password' => $passwordB];
examDraftSession($accountA);

$questionsA = examDraftSyntheticQuestions();
$sessionA = [
    'id' => 'quiz-' . bin2hex(random_bytes(8)),
    'board' => 'synthetic-board',
    'subject' => 'synthetic-subject',
    'topics' => [],
    'mode' => 'exam',
    'session_type' => 'exam_simulation',
    'question_count' => 2,
    'requested_question_count' => 2,
    'timer_mode' => 'timed',
    'time_limit_minutes' => 60,
    'time_limit_seconds' => 3600,
    'deadline_epoch' => time() + 3600,
    'time_expired' => false,
    'started_at' => gmdate(DATE_ATOM),
    'question_ids' => ['synthetic-exam-q1', 'synthetic-exam-q2'],
    'exam_answer_revision' => 0,
];
$_SESSION['quiz_session'] = $sessionA;
$_SESSION['questions'] = $questionsA;
$_SESSION['answers'] = [];
$_SESSION['currentQuestion'] = 0;
$_SESSION['exam_review_flags'] = [];
$_SESSION['exam_answer_revision'] = 0;

$draft = $service->createFromCurrentSession($sessionA, $questionsA);
$draftId = (string) $draft['id'];
$encodedDraft = json_encode($draft, JSON_THROW_ON_ERROR);
examDraftAssert(preg_match('/\Aexam-draft-[a-f0-9]{32}\z/', $draftId) === 1, 'Draft identifier is not opaque and high entropy.');
examDraftAssert($draft['user_id'] === $accountA['learner_id'], 'Draft was not bound to the verified learner identity.');
examDraftAssert($draft['question_ids'] === ['synthetic-exam-q1', 'synthetic-exam-q2'], 'Ordered question identifiers were not retained.');
examDraftAssert(!str_contains($encodedDraft, 'Synthetic stem')
    && !str_contains($encodedDraft, 'key explanation')
    && !str_contains($encodedDraft, 'Choice one'), 'Question text, choices, or answer-key explanations leaked into durable draft storage.');
examDraftAssert(!array_key_exists('answer', $draft) && !array_key_exists('questions', $draft), 'Correct answers or question snapshots were persisted in the durable draft.');
examDraftAssert($draft['quiz_session']['deadline_epoch'] === $sessionA['deadline_epoch'], 'Server deadline changed during draft creation.');

$rawAccountA = (new AccountRepository())->findByUsername((string) $accountA['username']);
$backupDraft = $draft;
unset($backupDraft['active_quiz_session_id']);
$backupCollectionNames = [
    'quiz_attempts', 'exam_drafts', 'weakness_summaries', 'flashcard_reviews',
    'learner_notes', 'question_issue_reports', 'study_calendar',
];
$backupCollections = [];
foreach ($backupCollectionNames as $collectionName) {
    $backupCollections[$collectionName] = $collectionName === 'exam_drafts' ? [$backupDraft] : [];
}
$includedNames = [...$backupCollectionNames, 'credential_profile'];
$backupCounts = array_fill_keys($backupCollectionNames, 0);
$backupCounts['exam_drafts'] = 1;
$backupCounts['credential_profile'] = 1;
$backupPayload = [
    'payload_schema_version' => 1,
    'manifest' => [
        'format' => 'boardprep-account-backup-payload-v1',
        'created_at' => gmdate(DATE_ATOM),
        'included_collections' => $includedNames,
        'record_counts' => $backupCounts,
        'collection_schema_versions' => array_fill_keys($includedNames, 1),
    ],
    'account' => [
        'account_id' => $rawAccountA['account_id'],
        'learner_id' => $rawAccountA['learner_id'],
        'username' => $rawAccountA['username'],
        'password_hash' => $rawAccountA['password_hash'],
        'created_at' => $rawAccountA['created_at'],
        'updated_at' => $rawAccountA['updated_at'],
        'auth_version' => $rawAccountA['auth_version'],
        'status' => 'active',
        'preferences' => $rawAccountA['preferences'],
        'recovery_code_hashes' => $rawAccountA['recovery_code_hashes'],
    ],
    'collections' => $backupCollections,
];
AccountRestoreService::validatePayload($backupPayload);
$unsafeBackupPayload = $backupPayload;
$unsafeBackupPayload['collections']['exam_drafts'][0]['active_quiz_session_id'] = $draft['active_quiz_session_id'];
examDraftExpect(RuntimeException::class,
    fn() => AccountRestoreService::validatePayload($unsafeBackupPayload),
    'An account restore payload retained an active quiz session binding');

$_SESSION['answers'] = ['synthetic-exam-q1' => 'B'];
$saved = $service->saveCurrentSessionState(0, (string) $sessionA['id']);
examDraftAssert($saved['revision'] === 1 && $saved['answers'] === ['synthetic-exam-q1' => 'B'], 'Answer did not persist with the next revision.');
$retry = $service->saveCurrentSessionState(0, (string) $sessionA['id']);
examDraftAssert($retry['revision'] === 1, 'An identical retry was not idempotent.');
$_SESSION['answers'] = ['synthetic-exam-q1' => 'A'];
examDraftExpect(ExamDraftConflictException::class,
    fn() => $service->saveCurrentSessionState(0, (string) $sessionA['id']),
    'A stale conflicting answer was accepted');
$storedA = App::storage()->find('exam-drafts', $draftId);
examDraftAssert(($storedA['answers']['synthetic-exam-q1'] ?? null) === 'B', 'A stale request overwrote the persisted answer.');

// A second account cannot discover or recover the first learner's draft by ID or quiz context.
examDraftSession($accountB);
examDraftAssert($service->currentSummary() === null, 'A different account could see the owner’s active-draft summary.');
examDraftExpect(ExamDraftUnavailableException::class,
    fn() => $service->recoverCurrentAccountDraft(),
    'A different account recovered another learner’s draft');
examDraftAssert(App::storage()->find('exam-drafts', $draftId)['user_id'] === $accountA['learner_id'], 'Cross-account rejection changed the owner record.');

// Losing the PHP session and authenticating again recovers the same draft without resetting time.
examDraftSession($accountA);
$recovered = $service->recoverCurrentAccountDraft();
examDraftAssert($recovered['status'] === 'resumed', 'The owner could not recover the active draft after PHP session loss.');
examDraftAssert($recovered['answers'] === ['synthetic-exam-q1' => 'B'], 'Recovery returned a different saved answer.');
examDraftAssert($recovered['quiz_session']['deadline_epoch'] === $sessionA['deadline_epoch'], 'Recovery extended the original server deadline.');
examDraftAssert($recovered['revision'] === 2 && $recovered['active_quiz_session_id'] !== $sessionA['id'], 'Recovery did not rotate its session binding and revision.');

// Repeated/concurrent recovery claims serialize and invalidate an older recovered session.
examDraftInstallSession($recovered, $questionsA);
$secondRecovery = $service->recoverCurrentAccountDraft();
examDraftAssert($secondRecovery['revision'] === 3
    && $secondRecovery['active_quiz_session_id'] !== $recovered['active_quiz_session_id'], 'A repeated recovery did not invalidate the prior session epoch.');
examDraftAssert(!$service->currentSessionIsAuthoritative(), 'A stale recovered PHP session remained authoritative.');
examDraftInstallSession($secondRecovery, $questionsA);
$sameOwnerRetry = $service->saveCurrentSessionState(2, (string) $secondRecovery['active_quiz_session_id']);
examDraftAssert($sameOwnerRetry['revision'] === 3, 'The current recovered session could not continue from its authoritative revision.');

// A finalized result makes the draft non-recoverable and cleans up its draft row.
examDraftSession($accountB);
$questionsB = examDraftSyntheticQuestions();
$sessionB = $sessionA;
$sessionB['id'] = 'quiz-' . bin2hex(random_bytes(8));
$sessionBStart = time();
$sessionB['started_at'] = gmdate(DATE_ATOM, $sessionBStart);
$sessionB['deadline_epoch'] = $sessionBStart + 1800;
$sessionB['time_limit_seconds'] = 1800;
$sessionB['time_limit_minutes'] = 30;
$_SESSION['quiz_session'] = $sessionB;
$_SESSION['questions'] = $questionsB;
$_SESSION['answers'] = [];
$_SESSION['currentQuestion'] = 0;
$_SESSION['exam_review_flags'] = [];
$draftB = $service->createFromCurrentSession($sessionB, $questionsB);
$result = App::storage()->create('attempts', [
    'id' => 'attempt-' . bin2hex(random_bytes(8)),
    'session_id' => $draftB['id'],
    'user_id' => $accountB['learner_id'],
    'completed' => true,
    'score' => 1,
    'total' => 2,
    'percentage' => 50,
]);
$finalized = $service->recoverCurrentAccountDraft();
examDraftAssert($finalized === ['status' => 'completed', 'attempt_id' => $result['id']], 'A submitted draft was reopened instead of resolving to its existing result.');
examDraftAssert(App::storage()->find('exam-drafts', (string) $draftB['id']) === null, 'A finalized draft was not removed after result verification.');

// Expired timed drafts retain the original deadline and cannot accept an answer save.
examDraftSession($accountA);
$storedA = App::storage()->find('exam-drafts', $draftId);
$expiredStart = time() - 3602;
$oldDeadline = $expiredStart + 3600;
App::storage()->update('exam-drafts', $draftId, [
    'quiz_session' => array_replace($storedA['quiz_session'], [
        'started_at' => gmdate(DATE_ATOM, $expiredStart),
        'deadline_epoch' => $oldDeadline,
    ]),
]);
$expired = $service->recoverCurrentAccountDraft();
examDraftAssert($expired['status'] === 'expired' && $expired['quiz_session']['deadline_epoch'] === $oldDeadline, 'An expired draft was revived or granted additional time.');
examDraftInstallSession($expired, $questionsA);
examDraftExpect(ExamDraftUnavailableException::class,
    fn() => $service->saveCurrentSessionState((int) $expired['revision'], (string) $expired['active_quiz_session_id']),
    'An answer was saved after the server deadline');

// A malformed owner record fails closed and is never overwritten by a recovery attempt.
$corrupt = $storedA;
$corrupt['question_ids'] = 'not-a-list';
App::storage()->update('exam-drafts', $draftId, $corrupt);
$unavailable = $service->currentSummary();
examDraftAssert(($unavailable['status'] ?? null) === 'unavailable' && ($unavailable['id'] ?? null) === $draftId,
    'A corrupted draft did not produce the safe owner-scoped unavailable state.');
$afterCorruption = App::storage()->find('exam-drafts', $draftId);
examDraftAssert(($afterCorruption['question_ids'] ?? null) === 'not-a-list', 'Corrupt draft storage was overwritten during fail-closed handling.');
examDraftAssert($service->discardOwnedDraft($draftId)
    && App::storage()->find('exam-drafts', $draftId) === null,
    'An owner could not safely discard an unverifiable draft.');

session_unset();
session_write_close();
examDraftRemoveFixture($root);
echo "[PASS] {$backend}: synthetic authenticated ownership, lost-session recovery, answer persistence/retry, stale-write rejection, repeated recovery, unchanged deadline, submitted/expired/corrupt draft rejection and discard, and answer-key exclusion.\n";
