<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

use App\Core\App;
use App\Services\Accounts\AccountDataInventoryService;
use App\Services\Accounts\AccountDataService;
use App\Services\Accounts\AccountDeletionService;
use App\Services\Accounts\AccountLifecycleLock;
use App\Services\Accounts\AccountRepository;
use App\Services\Accounts\AccountService;
use App\Services\Accounts\AccountSessionService;

function lifecycleAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$driver = (string) ($argv[1] ?? 'json');
if (!in_array($driver, ['json', 'sqlite'], true)) {
    throw new RuntimeException('Choose json or sqlite fixture mode.');
}
if ($driver === 'sqlite' && !extension_loaded('pdo_sqlite')) {
    throw new RuntimeException('SQLite lifecycle verification requires pdo_sqlite.');
}

$fixture = sys_get_temp_dir() . '/boardprep-account-lifecycle-' . bin2hex(random_bytes(8));
$storage = $fixture . '/storage';
$sessions = $fixture . '/sessions';
mkdir($storage, 0700, true);
mkdir($sessions, 0700, true);
$environment = [
    'APP_ENV' => 'testing',
    'APP_DEBUG' => 'false',
    'BOARDPREP_ACCOUNTS_ENABLED' => 'true',
    'DB_DRIVER' => $driver,
    'APP_STORAGE_PATH' => $storage,
    'DB_SQLITE_PATH' => $fixture . '/runtime.sqlite',
    'APP_SESSION_PATH' => $sessions,
];
foreach ($environment as $key => $value) {
    putenv($key . '=' . $value);
    $_ENV[$key] = $value;
}
$_SERVER['REMOTE_ADDR'] = '192.0.2.193';
App::boot();

$repo = new AccountRepository();
$accounts = new AccountService($repo);

// Inject bounded storage failures without touching device storage or learner fixtures.
$atomicFixture = $fixture . '/atomic-credentials';
$atomicRepository = new AccountRepository($atomicFixture);
$atomicRecord = [
    'account_id' => 'account-' . str_repeat('d', 32),
    'learner_id' => 'account-learner-' . str_repeat('d', 32),
    'username' => 'atomic-fixture',
    'status' => 'active',
    'updated_at' => 'before',
];
lifecycleAssert($atomicRepository->create($atomicRecord), 'Atomic write fixture account could not be created.');
$credentialFile = $atomicFixture . '/accounts.json';
$credentialHash = hash_file('sha256', $credentialFile);
foreach ([
    'before_temporary_write' => 'synthetic disk full',
    'before_atomic_commit' => 'synthetic write permission failure',
] as $faultStage => $faultMessage) {
    $faultRepository = new AccountRepository($atomicFixture, static function (string $stage) use ($faultStage, $faultMessage): void {
        if ($stage === $faultStage) {
            throw new RuntimeException($faultMessage);
        }
    });
    try {
        $faultRepository->updateById($atomicRecord['account_id'], static function (array $account): array {
            $account['updated_at'] = 'must-not-commit';
            return $account;
        });
        throw new RuntimeException('Injected credential write failure was ignored.');
    } catch (RuntimeException $exception) {
        lifecycleAssert(str_contains($exception->getMessage(), $faultMessage), 'Injected credential write failure had an unexpected result.');
    }
    lifecycleAssert(hash_file('sha256', $credentialFile) === $credentialHash, 'Failed credential write changed the original JSON state.');
    lifecycleAssert((glob($atomicFixture . '/.accounts-*') ?: []) === [], 'Failed credential write left a temporary plaintext account file.');
}
$corruptFixture = $fixture . '/corrupt-credentials';
mkdir($corruptFixture, 0700, true);
file_put_contents($corruptFixture . '/accounts.json', '{corrupt credential fixture');
$corruptRejected = false;
try {
    (new AccountRepository($corruptFixture))->findById('account-' . str_repeat('e', 32));
} catch (RuntimeException $exception) {
    $corruptRejected = str_contains($exception->getMessage(), 'corrupt');
}
lifecycleAssert($corruptRejected, 'Corrupt credential JSON did not fail closed before account mutation.');

$passwordA = 'Batch693 synthetic account A password';
$passwordB = 'Batch693 synthetic account B password';
$passwordD = 'Batch693 synthetic delete account password';
$passwordE = 'Batch693 synthetic recovery account password';
$passwordRace = 'Batch693 synthetic deletion race password';
$registeredA = $accounts->register('batch693-a', $passwordA);
$registeredB = $accounts->register('batch693-b', $passwordB);
$registeredD = $accounts->register('batch693-d', $passwordD);
$registeredE = $accounts->register('batch693-e', $passwordE);
$registeredRace = $accounts->register('batch693-race', $passwordRace);
lifecycleAssert(is_array($registeredA['account']) && is_array($registeredB['account'])
    && is_array($registeredD['account']) && is_array($registeredE['account']) && is_array($registeredRace['account']), 'Synthetic fixture account creation failed.');
$rawA = $repo->findByUsername('batch693-a');
$rawB = $repo->findByUsername('batch693-b');
$rawD = $repo->findByUsername('batch693-d');
$rawE = $repo->findByUsername('batch693-e');
$rawRace = $repo->findByUsername('batch693-race');
lifecycleAssert(is_array($rawA) && is_array($rawB) && is_array($rawD) && is_array($rawE) && is_array($rawRace), 'Synthetic account records were unavailable.');
$learnerA = $rawA['learner_id'];
$learnerB = $rawB['learner_id'];
$learnerD = $rawD['learner_id'];
$learnerE = $rawE['learner_id'];
$learnerRace = $rawRace['learner_id'];
$anonymousC = 'learner-' . str_repeat('c', 32);

$seed = static function (string $learnerId, string $suffix, int $value) use ($driver): void {
    App::storage()->create('attempts', [
        'id' => 'batch693-attempt-' . $suffix,
        'user_id' => $learnerId,
        'session_id' => 'synthetic-quiz-session-' . $suffix,
        'score' => $value,
        'total' => 10,
        'completed' => true,
    ]);
    App::storage()->create('weakness', [
        'id' => $learnerId . '|synthetic-topic-' . $suffix,
        'user_id' => $learnerId,
        'topic' => 'synthetic-topic-' . $suffix,
        'correct' => $value,
        'wrong' => 10 - $value,
    ]);
    App::storage()->create('flashcard_state', [
        'id' => $learnerId . '|synthetic-card-' . $suffix,
        'user_id' => $learnerId,
        'card_key' => 'synthetic-card-' . $suffix,
        'review_count' => $value,
        'state' => 'review',
    ]);
};
$seed($learnerA, 'a', 7);
$seed($learnerB, 'b', 4);
$seed($learnerD, 'd1', 2);
$seed($learnerD, 'd2', 8);
$seed($learnerE, 'e', 6);
$seed($learnerRace, 'race', 5);
App::storage()->create('attempts', [
    'id' => 'batch693-anonymous-c',
    'user_id' => $anonymousC,
    'session_id' => 'anonymous-c-session',
    'score' => 3,
    'total' => 10,
]);

$questionPath = dirname(__DIR__, 2) . '/storage/questions.json';
$questionsBefore = hash_file('sha256', $questionPath);
$stableOwnedHash = static function (string $learnerId): string {
    $collections = [];
    foreach (['attempts', 'weakness', 'flashcard_state'] as $name) {
        $collections[$name] = App::storage()->where($name, ['user_id' => $learnerId]);
    }
    return hash('sha256', serialize($collections));
};
$hashA = $stableOwnedHash($learnerA);
$hashB = $stableOwnedHash($learnerB);
$hashC = $stableOwnedHash($anonymousC);

try {
    session_save_path($sessions);
    ini_set('session.use_cookies', '0');
    session_id(bin2hex(random_bytes(16)));
    session_start();

    lifecycleAssert($accounts->signIn('batch693-d', $passwordD), 'Synthetic deletion account could not sign in.');
    $inventory = (new AccountDataInventoryService())->inspectAuthenticated();
    lifecycleAssert($inventory['collections']['quiz_attempts']['count'] === 2
        && $inventory['collections']['weakness_summaries']['count'] === 2
        && $inventory['collections']['flashcard_reviews']['count'] === 2,
        'Account data inventory did not accurately report owned collections.');
    lifecycleAssert(($inventory['derived']['current_storage_non_account_owned_counts']['anonymous_learner_records']['quiz_attempts'] ?? null) === 1,
        'Account inventory did not keep anonymous learner records separate from account-owned data.');
    $export = (new AccountDataService())->exportCurrent();
    lifecycleAssert(($export['manifest']['record_counts']['account_profile'] ?? null) === 1
        && ($export['manifest']['record_counts']['quiz_attempts'] ?? null) === 2
        && !isset($export['account']['password_hash'], $export['account']['recovery_code_hashes'])
        && !str_contains(json_encode($export, JSON_THROW_ON_ERROR), 'synthetic-quiz-session-d1'),
        'Account export manifest or secret/session exclusions were incorrect.');

    lifecycleAssert($accounts->requestDeletion($passwordD), 'Password-confirmed deletion request was not recorded.');
    lifecycleAssert(($repo->findById($rawD['account_id'])['status'] ?? null) === 'deletion_requested', 'Deletion request state was not explicit.');
    lifecycleAssert(!$accounts->signIn('batch693-d', $passwordD), 'A deletion-requested account could sign in.');
    $writeRejected = false;
    try {
        AccountLifecycleLock::withWritableLearner($learnerD, static function () use ($learnerD): void {
            App::storage()->create('attempts', ['id' => 'batch693-late-write', 'user_id' => $learnerD]);
        });
    } catch (RuntimeException) {
        $writeRejected = true;
    }
    lifecycleAssert($writeRejected, 'A new account-owned write was allowed after deletion request.');

    $failedOnce = false;
    $deletion = new AccountDeletionService(static function (string $collection) use (&$failedOnce): void {
        if ($collection === 'quiz_attempts' && !$failedOnce) {
            $failedOnce = true;
            throw new RuntimeException('synthetic interrupted deletion');
        }
    });
    try {
        $deletion->process($rawD['account_id']);
        throw new RuntimeException('Injected deletion interruption did not fail.');
    } catch (RuntimeException $exception) {
        lifecycleAssert(str_contains($exception->getMessage(), 'interrupted'), 'Unexpected deletion interruption result.');
    }
    lifecycleAssert(($repo->findById($rawD['account_id'])['status'] ?? null) === 'deletion_failed', 'Interrupted deletion was not left in a recoverable failed state.');
    lifecycleAssert(App::storage()->where('attempts', ['user_id' => $learnerD]) === [], 'Deletion failure left the checkpointed collection behind.');
    lifecycleAssert(count(App::storage()->where('weakness', ['user_id' => $learnerD])) === 2, 'Deletion failure damaged a later collection before its checkpoint.');

    $completed = (new AccountDeletionService())->process($rawD['account_id']);
    lifecycleAssert($completed['status'] === 'deletion_completed', 'Deletion retry did not complete.');
    lifecycleAssert(($repo->findById($rawD['account_id'])['status'] ?? null) === 'deletion_completed', 'Deletion did not retain the minimal idempotency tombstone.');
    $tombstone = $repo->findById($rawD['account_id']);
    lifecycleAssert(!isset($tombstone['username'], $tombstone['password_hash'], $tombstone['recovery_code_hashes'], $tombstone['preferences']), 'Deletion tombstone retained credentials or profile data.');
    lifecycleAssert($repo->findByUsername('batch693-d') === null, 'Deleted account credentials remained discoverable by username.');
    lifecycleAssert(App::storage()->where('attempts', ['user_id' => $learnerD]) === []
        && App::storage()->where('weakness', ['user_id' => $learnerD]) === []
        && App::storage()->where('flashcard_state', ['user_id' => $learnerD]) === [],
        'Deletion completed while owned records remained.');
    lifecycleAssert((new AccountDeletionService())->process($rawD['account_id'])['already_completed'], 'Duplicate deletion retry was not idempotent.');
    lifecycleAssert($stableOwnedHash($learnerA) === $hashA && $stableOwnedHash($learnerB) === $hashB
        && $stableOwnedHash($anonymousC) === $hashC, 'Deletion changed Account A, Account B, or anonymous Account C data.');
    lifecycleAssert(hash_file('sha256', $questionPath) === $questionsBefore, 'Deletion changed canonical/global educational content.');
    lifecycleAssert(!$accounts->recover('batch693-d', $registeredD['recovery_codes'][0], 'Batch693 deleted account recovery password'), 'A deleted account recovery code restored credentials.');
    lifecycleAssert($accounts->signIn('batch693-a', $passwordA), 'Account A could not sign in after Account D deletion.');
    $exportA = (new AccountDataService())->exportCurrent();
    $attemptIdsA = array_column($exportA['records']['quiz_attempts'], 'id');
    lifecycleAssert(in_array('batch693-attempt-a', $attemptIdsA, true)
        && !in_array('batch693-attempt-b', $attemptIdsA, true), 'Account A export included Account B records.');
    lifecycleAssert($accounts->signIn('batch693-b', $passwordB), 'Account B could not sign in for reciprocal export isolation.');
    $exportB = (new AccountDataService())->exportCurrent();
    $attemptIdsB = array_column($exportB['records']['quiz_attempts'], 'id');
    lifecycleAssert(in_array('batch693-attempt-b', $attemptIdsB, true)
        && !in_array('batch693-attempt-a', $attemptIdsB, true), 'Account B export included Account A records.');
    foreach (['attempts', 'weakness', 'flashcard_state'] as $collectionName) {
        foreach (App::storage()->all($collectionName) as $record) {
            if (is_array($record) && preg_match('/\\Aaccount-learner-[a-f0-9]{32}\\z/', (string) ($record['user_id'] ?? '')) === 1) {
                lifecycleAssert($repo->findByLearnerId((string) $record['user_id']) !== null, 'A learner record referenced an unknown account identity.');
            }
        }
    }

    lifecycleAssert($accounts->signIn('batch693-race', $passwordRace)
        && $accounts->requestDeletion($passwordRace), 'Deletion race fixture did not enter the requested state.');
    $raceWorker = __DIR__ . '/AccountDeletionRaceWorker.php';
    $deleteProcess = proc_open([PHP_BINARY, $raceWorker, 'delete', $rawRace['account_id'], $learnerRace], [
        0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w'],
    ], $deletePipes);
    lifecycleAssert(is_resource($deleteProcess), 'Could not start isolated deletion race worker.');
    lifecycleAssert(trim((string) fgets($deletePipes[1])) === 'DELETION_LOCK_HELD', 'Deletion race worker did not hold the lifecycle lock before contenders started.');

    $loginProcess = proc_open([PHP_BINARY, $raceWorker, 'login', $rawRace['account_id'], $learnerRace], [
        0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w'],
    ], $loginPipes);
    lifecycleAssert(is_resource($loginProcess), 'Could not start isolated login race worker.');
    fwrite($loginPipes[0], "batch693-race\n{$passwordRace}\n");
    fclose($loginPipes[0]);

    $writeProcess = proc_open([PHP_BINARY, $raceWorker, 'write', $rawRace['account_id'], $learnerRace], [
        0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w'],
    ], $writePipes);
    lifecycleAssert(is_resource($writeProcess), 'Could not start isolated account-write race worker.');

    $deleteRemainder = trim((string) stream_get_contents($deletePipes[1]));
    $deleteError = preg_replace('/^WARNING: linker:.*(?:\r?\n|$)/m', '', trim((string) stream_get_contents($deletePipes[2]))) ?? '';
    fclose($deletePipes[1]);
    fclose($deletePipes[2]);
    $deleteStatus = proc_close($deleteProcess);
    $loginOutput = trim((string) stream_get_contents($loginPipes[1]));
    $loginError = preg_replace('/^WARNING: linker:.*(?:\r?\n|$)/m', '', trim((string) stream_get_contents($loginPipes[2]))) ?? '';
    fclose($loginPipes[1]);
    fclose($loginPipes[2]);
    $loginStatus = proc_close($loginProcess);
    $writeOutput = trim((string) stream_get_contents($writePipes[1]));
    $writeError = preg_replace('/^WARNING: linker:.*(?:\r?\n|$)/m', '', trim((string) stream_get_contents($writePipes[2]))) ?? '';
    fclose($writePipes[1]);
    fclose($writePipes[2]);
    $writeStatus = proc_close($writeProcess);
    lifecycleAssert($deleteStatus === 0 && $deleteRemainder === 'deletion_completed' && $deleteError === '', 'Concurrent deletion did not complete cleanly.');
    lifecycleAssert($loginStatus === 0 && $loginOutput === 'LOGIN_DENIED' && $loginError === '', 'A concurrent login succeeded during or after deletion.');
    lifecycleAssert($writeStatus === 0 && $writeOutput === 'WRITE_DENIED' && $writeError === '', 'A concurrent learner write succeeded during or after deletion.');
    lifecycleAssert(App::storage()->where('attempts', ['user_id' => $learnerRace]) === [], 'A concurrent deletion/write race left a dangling learner record.');

    // Two independent workers race to consume one synthetic recovery code.
    lifecycleAssert($accounts->signIn('batch693-e', $passwordE), 'Recovery fixture could not establish a stale session.');
    $_SESSION['boardprep_account_auth']['last_activity'] = time() - 31 * 60;
    lifecycleAssert(AccountSessionService::current() === null, 'Expired account session E remained authenticated.');
    lifecycleAssert($accounts->signIn('batch693-e', $passwordE), 'Recovery fixture could not sign in after idle expiry.');
    $staleRecoverySession = $_SESSION['boardprep_account_auth'];
    $recoveryCode = $registeredE['recovery_codes'][0];
    $newRecoveryPassword = 'Batch693 recovered synthetic password';
    $workers = [];
    for ($worker = 0; $worker < 2; $worker++) {
        $command = [PHP_BINARY, __DIR__ . '/AccountRecoveryConcurrencyWorker.php', $storage, $driver, 'batch693-e'];
        $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        lifecycleAssert(is_resource($process), 'Could not start concurrent recovery worker.');
        fwrite($pipes[0], $recoveryCode . "\n" . $newRecoveryPassword . "\n");
        fclose($pipes[0]);
        $workers[] = [$process, $pipes];
    }
    $won = 0;
    foreach ($workers as [$process, $pipes]) {
        $stdout = trim((string) stream_get_contents($pipes[1]));
        $stderr = preg_replace('/^WARNING: linker:.*(?:\r?\n|$)/m', '', trim((string) stream_get_contents($pipes[2]))) ?? '';
        fclose($pipes[1]);
        fclose($pipes[2]);
        $status = proc_close($process);
        lifecycleAssert($status === 0 && $stderr === '' && in_array($stdout, ['WON', 'LOST'], true), 'Concurrent recovery worker failed without safe output.');
        if ($stdout === 'WON') $won++;
    }
    lifecycleAssert($won === 1, 'Concurrent recovery allowed zero or multiple uses of a single-use code.');
    $_SESSION['boardprep_account_auth'] = $staleRecoverySession;
    $_SESSION['learner_identity'] = $learnerE;
    lifecycleAssert(AccountSessionService::current() === null, 'Password recovery did not revoke the stale authenticated session.');
    lifecycleAssert(!$accounts->recover('batch693-e', $recoveryCode, 'Batch693 another recovery password'), 'Concurrent recovery code replay was accepted.');

    echo "[PASS] {$driver} storage: owned-data inventory/export parity and credential/session exclusions.\n";
    echo "[PASS] {$driver} storage: deletion request, write fence, journal interruption/retry, tombstone cleanup, idempotency, and A/B/anonymous/global isolation.\n";
    echo "[PASS] {$driver} storage: concurrent recovery consumes exactly once and revokes stale sessions.\n";
    echo "[PASS] {$driver} storage: injected disk-full/write-permission faults and corrupt credential JSON fail closed without changing prior credentials.\n";
    echo "[PASS] {$driver} storage: a held deletion lifecycle lock defeats concurrent login and learner-write attempts.\n";
} finally {
    if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($fixture, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $entry) {
        if ($entry->isDir() && !$entry->isLink()) @rmdir($entry->getPathname());
        else @unlink($entry->getPathname());
    }
    @rmdir($fixture);
    foreach ($environment as $key => $_value) {
        putenv($key);
        unset($_ENV[$key]);
    }
}
