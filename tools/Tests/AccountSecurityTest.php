<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

use App\Core\App;
use App\Services\Accounts\AccountPasswordPolicy;
use App\Services\Accounts\AccountRepository;
use App\Services\Accounts\AccountService;
use App\Services\Accounts\AccountSessionService;
use App\Services\Learning\LearnerIdentityService;
use App\Services\Privacy\AnonymousLearnerDataService;
use App\Services\Profile\LearnerPreferenceService;

function accountAssert(bool $ok, string $message): void
{
    if (!$ok) {
        throw new RuntimeException($message);
    }
}

$fixture = sys_get_temp_dir() . '/boardprep-accounts-' . bin2hex(random_bytes(8));
$storage = $fixture . '/storage';
$sessions = $fixture . '/sessions';
mkdir($storage, 0700, true);
mkdir($sessions, 0700, true);
foreach (['boards.json', 'subjects.json', 'board-subjects.json', 'questions.json', 'question-eligibility.json'] as $file) {
    $source = dirname(__DIR__, 2) . '/storage/' . $file;
    if (is_file($source)) copy($source, $storage . '/' . $file);
}
putenv('APP_ENV=testing');
putenv('APP_DEBUG=false');
putenv('BOARDPREP_ACCOUNTS_ENABLED=true');
putenv('DB_DRIVER=json');
putenv('APP_STORAGE_PATH=' . $storage);
putenv('APP_SESSION_PATH=' . $sessions);
$_ENV['APP_ENV'] = 'testing';
$_ENV['BOARDPREP_ACCOUNTS_ENABLED'] = 'true';
$_ENV['DB_DRIVER'] = 'json';
$_ENV['APP_STORAGE_PATH'] = $storage;
$_ENV['APP_SESSION_PATH'] = $sessions;
$_SERVER['REMOTE_ADDR'] = '192.0.2.69';
App::boot();

$repository = new AccountRepository();
$service = new AccountService($repository);
$passwordA = 'Batch692 Learner A safe passphrase';
$anonymousId = 'learner-' . str_repeat('a', 32);

try {
    session_save_path($sessions);
    session_id(bin2hex(random_bytes(16)));
    session_start();
    $_SESSION['learner_identity'] = $anonymousId;
    $_SESSION['learner_preferences'] = ['preferred_board' => 'LET'];
    $_SESSION['questions'] = [['id' => 'anonymous-active-question']];
    $_SESSION['quiz_session'] = ['id' => 'anonymous-active-session'];
    $anonymousRecord = [
        'id' => 'account-test-anonymous-attempt',
        'user_id' => $anonymousId,
        'session_id' => 'anonymous-before-login',
        'score' => 1,
        'total' => 1,
    ];
    App::storage()->create('attempts', $anonymousRecord);

    $registeredA = $service->register('LearnerA', $passwordA);
    accountAssert(count($registeredA['recovery_codes']) === 8, 'Registration did not issue eight one-time recovery codes.');
    accountAssert($registeredA['account']['username'] === 'learnera', 'Username normalization failed.');
    $duplicateA = $service->register('learnera', $passwordA);
    accountAssert($duplicateA['account'] === null, 'Duplicate registration was not rejected.');
    accountAssert(count($duplicateA['recovery_codes']) === 8, 'Duplicate registration did not preserve an enumeration-safe response shape.');
    accountAssert($repository->countAccounts() === 1, 'Duplicate registration created another account.');

    $accountFile = $storage . '/private/learner-accounts/accounts.json';
    $privateMode = fileperms(dirname($accountFile)) & 0777;
    $fileMode = fileperms($accountFile) & 0777;
    accountAssert(($privateMode & 0077) === 0 && ($fileMode & 0077) === 0, 'Credential storage is not private to its owner.');
    $storedJson = (string) file_get_contents($accountFile);
    accountAssert(!str_contains($storedJson, $passwordA), 'Raw password was written to account storage.');
    foreach ($registeredA['recovery_codes'] as $code) {
        accountAssert(!str_contains($storedJson, str_replace('-', '', $code)), 'Raw recovery code was written to account storage.');
    }
    $rawA = $repository->findByUsername('learnera');
    accountAssert(is_array($rawA) && password_verify($passwordA, $rawA['password_hash']), 'Password hash did not verify through PHP password APIs.');
    $duplicateLearnerIdentity = [
        'account_id' => 'account-' . str_repeat('c', 32),
        'learner_id' => $rawA['learner_id'],
        'username' => 'identitycollision',
        'password_hash' => AccountPasswordPolicy::hash('isolated identity collision fixture'),
        'created_at' => gmdate(DATE_ATOM),
        'updated_at' => gmdate(DATE_ATOM),
        'auth_version' => 1,
        'status' => 'active',
        'deletion_requested_at' => null,
        'preferences' => AccountService::defaultPreferences(),
        'recovery_code_hashes' => [],
    ];
    accountAssert(!$repository->create($duplicateLearnerIdentity), 'A second account reused an existing learner identity.');
    try {
        $repository->updateById((string) $rawA['account_id'], static function (array $account): array {
            $account['learner_id'] = 'account-learner-' . str_repeat('b', 32);
            return $account;
        });
        throw new RuntimeException('Account learner identity could be reassigned after creation.');
    } catch (RuntimeException $exception) {
        accountAssert(str_contains($exception->getMessage(), 'preserve its identity'), 'Unexpected identity update rejection.');
    }
    $backupPlan = \App\Services\Operations\BackupService::create(
        dirname(__DIR__, 2),
        $fixture . '/account-backup-dry-run',
        true
    );
    accountAssert(!in_array('json/private/learner-accounts/accounts.json', array_column($backupPlan['manifest']['files'] ?? [], 'relative'), true), 'The unencrypted runtime backup plan included private account credentials.');

    $oldSession = session_id();
    accountAssert(!$service->signIn('learnera', 'wrong password'), 'Wrong password was accepted.');
    accountAssert($service->signIn('learnera', $passwordA), 'Correct credentials were rejected.');
    accountAssert(session_id() !== $oldSession, 'Successful login did not rotate the session ID.');
    accountAssert(!is_file($sessions . '/sess-' . $oldSession), 'Pre-login session file was not invalidated.');
    $learnerA = LearnerIdentityService::current();
    accountAssert($learnerA === $rawA['learner_id'] && $learnerA !== $anonymousId, 'Login did not switch to the separate server-issued account learner identity.');
    accountAssert(($_SESSION['anonymous_learner_identity'] ?? null) === $anonymousId, 'The pre-login anonymous identity was not kept separate.');
    accountAssert(!isset($_SESSION['questions'], $_SESSION['quiz_session']), 'An anonymous active quiz remained active inside the account session.');
    accountAssert(is_array($_SESSION['anonymous_pending_quiz'] ?? null), 'Anonymous in-progress quiz context was discarded instead of isolated.');

    try {
        AnonymousLearnerDataService::current();
        throw new RuntimeException('Anonymous privacy controls were allowed to operate on an authenticated account.');
    } catch (RuntimeException $exception) {
        accountAssert(str_contains($exception->getMessage(), 'Signed-in learner data'), 'Unexpected anonymous privacy boundary error.');
    }

    $preferences = [
        'preferred_board' => 'CSE',
        'preferred_subject' => 'general-information',
        'let_level' => '',
        'let_specialization' => '',
        'civil_service_level' => 'PROFESSIONAL',
        'subject_interests' => ['english'],
    ];
    accountAssert($service->updatePreferences($preferences), 'Authenticated account preference update failed.');
    App::storage()->create('attempts', [
        'id' => 'account-test-attempt-a',
        'user_id' => $learnerA,
        'session_id' => 'account-a-session',
        'score' => 4,
        'total' => 5,
    ]);
    $oldAuth = $_SESSION['boardprep_account_auth'];

    session_write_close();
    session_id(bin2hex(random_bytes(16)));
    session_start();
    accountAssert($service->signIn('learnera', $passwordA), 'Independent second session could not sign in.');
    accountAssert(LearnerPreferenceService::get() === $preferences, 'Saved account preferences did not return in a second session.');
    accountAssert(LearnerIdentityService::current() === $learnerA, 'Account learner identity changed across sessions.');

    $registeredB = $service->register('LearnerB', 'Batch692 Learner B safe passphrase');
    accountAssert(is_array($registeredB['account']), 'A second distinct account could not register.');
    AccountSessionService::logout();
    accountAssert($service->signIn('learnerb', 'Batch692 Learner B safe passphrase'), 'Second account could not sign in.');
    $learnerB = LearnerIdentityService::current();
    accountAssert($learnerB !== $learnerA, 'Two accounts received the same learner identity.');
    App::storage()->create('attempts', [
        'id' => 'account-test-attempt-b',
        'user_id' => $learnerB,
        'session_id' => 'account-b-session',
        'score' => 2,
        'total' => 5,
    ]);
    $exportB = (new \App\Services\Accounts\AccountDataService())->exportCurrent();
    accountAssert(count($exportB['records']['quiz_attempts']) === 1, 'Account B export included another account’s attempt.');
    accountAssert($exportB['records']['quiz_attempts'][0]['score'] === 2, 'Account B export returned the wrong account’s attempt.');
    accountAssert(!isset($exportB['account']['account_id'], $exportB['records']['quiz_attempts'][0]['user_id']), 'Account export exposed an internal account/learner ID.');
    accountAssert(App::storage()->find('attempts', 'account-test-anonymous-attempt')['user_id'] === $anonymousId, 'Anonymous attempt ownership changed during sign-in.');
    accountAssert(App::storage()->find('attempts', 'account-test-attempt-a')['user_id'] === $learnerA, 'Account A attempt ownership changed.');
    accountAssert(App::storage()->find('attempts', 'account-test-attempt-b')['user_id'] === $learnerB, 'Account B attempt ownership changed.');

    $registeredC = $service->register('LearnerC', 'Batch692 Learner C safe passphrase');
    accountAssert(is_array($registeredC['account']), 'Rate-limit fixture account could not be created.');
    AccountSessionService::logout();
    for ($attempt = 0; $attempt < 8; $attempt++) {
        accountAssert(!$service->signIn('learnerc', 'incorrect password'), 'Wrong-password attempt unexpectedly succeeded.');
    }
    accountAssert(!$service->signIn('learnerc', 'Batch692 Learner C safe passphrase'), 'Repeated guesses were not rate-limited.');

    $registeredD = $service->register('LearnerD', 'Batch692 Learner D safe passphrase');
    accountAssert(is_array($registeredD['account']), 'Recovery fixture account could not be created.');
    $recoveryCode = $registeredD['recovery_codes'][0];
    accountAssert($service->recover('learnerd', $recoveryCode, 'Batch692 D recovered passphrase'), 'Valid recovery code was not accepted.');
    accountAssert(!$service->recover('learnerd', $recoveryCode, 'Batch692 D replayed passphrase'), 'Consumed recovery code could be replayed.');
    AccountSessionService::logout();
    accountAssert($service->signIn('learnerd', 'Batch692 D recovered passphrase'), 'Recovered password could not sign in.');
    accountAssert($service->requestDeletion('Batch692 D recovered passphrase'), 'Password-confirmed deletion request was not recorded.');
    accountAssert(AccountSessionService::current() === null, 'Deletion-requested account kept an authenticated session.');
    accountAssert(($repository->findByUsername('learnerd')['status'] ?? null) === 'deletion_requested', 'Deletion request did not disable future sign-in.');
    accountAssert(!$service->signIn('learnerd', 'Batch692 D recovered passphrase'), 'Deletion-requested account could sign in again.');

    // A password change advances the account epoch and makes other sessions stale.
    AccountSessionService::logout();
    accountAssert($service->signIn('learnera', $passwordA), 'Account A could not sign in for password change.');
    $staleAuth = $_SESSION['boardprep_account_auth'];
    accountAssert($service->changePassword($passwordA, 'Batch692 A changed secure passphrase'), 'Password change failed.');
    accountAssert((int) $_SESSION['boardprep_account_auth']['auth_version'] > (int) $staleAuth['auth_version'], 'Password change did not advance the session revocation epoch.');
    session_write_close();
    session_id(bin2hex(random_bytes(16)));
    session_start();
    $_SESSION['boardprep_account_auth'] = $staleAuth;
    $_SESSION['learner_identity'] = $staleAuth['learner_id'];
    accountAssert(AccountSessionService::current() === null, 'A stale pre-password-change session remained authenticated.');
    accountAssert(!$service->signIn('learnera', $passwordA), 'The old password remained valid after change.');
    accountAssert($service->signIn('learnera', 'Batch692 A changed secure passphrase'), 'The changed password could not sign in.');

    $_SESSION['quiz_session'] = ['id' => 'account-active-session'];
    $_SESSION['questions'] = [['id' => 'account-active-question']];
    accountAssert($service->changePassword('Batch692 A changed secure passphrase', 'Batch692 A second changed passphrase'), 'Second password transition failed.');
    accountAssert(($_SESSION['quiz_session']['id'] ?? '') === 'account-active-session'
        && isset($_SESSION['questions'][0]['id']),
        'A security transition changed authenticated in-progress quiz ownership or state.');

    $_SERVER['REMOTE_ADDR'] = '198.51.100.55';
    for ($attempt = 0; $attempt < 8; $attempt++) {
        accountAssert(!$service->requestDeletion('incorrect current password'), 'An incorrect step-up password was accepted.');
    }
    accountAssert(!$service->requestDeletion('Batch692 A second changed passphrase'), 'Sensitive account actions were not rate-limited after repeated password guesses.');
    accountAssert(($repository->findByUsername('learnera')['status'] ?? null) === 'active', 'A rate-limited deletion attempt changed account status.');
    $_SERVER['REMOTE_ADDR'] = '192.0.2.69';

    $expiredAuth = $_SESSION['boardprep_account_auth'];
    $expiredAuth['last_activity'] = time() - 31 * 60;
    $_SESSION['boardprep_account_auth'] = $expiredAuth;
    accountAssert(AccountSessionService::current() === null, 'An idle-expired account session remained authenticated.');

    accountAssert($service->signIn('learnera', 'Batch692 A second changed passphrase'), 'Account could not sign in after idle expiry.');
    $expiredAuth = $_SESSION['boardprep_account_auth'];
    $expiredAuth['started_at'] = time() - 13 * 3600;
    $_SESSION['boardprep_account_auth'] = $expiredAuth;
    accountAssert(AccountSessionService::current() === null, 'An absolute-expired account session remained authenticated.');

    accountAssert($service->signIn('learnera', 'Batch692 A second changed passphrase'), 'Account could not sign in after absolute expiry.');
    $tampered = $_SESSION['boardprep_account_auth'];
    $tampered['account_id'] = 'account-' . str_repeat('f', 32);
    $_SESSION['boardprep_account_auth'] = $tampered;
    accountAssert(AccountSessionService::current() === null, 'Tampered account identifier remained authorized.');

    $concurrentDirectory = $fixture . '/concurrent';
    mkdir($concurrentDirectory, 0700, true);
    $processes = [];
    for ($worker = 1; $worker <= 4; $worker++) {
        $command = [PHP_BINARY, dirname(__FILE__) . '/AccountConcurrencyWorker.php', $concurrentDirectory, (string) $worker];
        $process = proc_open($command, [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        accountAssert(is_resource($process), 'Could not start concurrent account-registration worker.');
        $processes[] = [$process, $pipes];
    }
    $createdCount = 0;
    foreach ($processes as [$process, $pipes]) {
        $output = trim((string) stream_get_contents($pipes[1]));
        $error = trim((string) stream_get_contents($pipes[2]));
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($process);
        $unexpectedError = preg_replace('/^WARNING: linker:.*(?:\r?\n|$)/m', '', $error) ?? $error;
        accountAssert($exit === 0 && trim($unexpectedError) === '', 'A concurrent account worker failed: ' . $error);
        if ($output === 'CREATED') $createdCount++;
    }
    $concurrentRepository = new AccountRepository($concurrentDirectory);
    accountAssert($createdCount === 1 && $concurrentRepository->countAccounts() === 1, 'Concurrent duplicate registration was not serialized atomically.');

    accountAssert($concurrentRepository->create([
        'account_id' => 'account-' . str_repeat('e', 32),
        'learner_id' => 'account-learner-' . str_repeat('e', 32),
        'username' => 'concurrent-counter',
        'password_hash' => password_hash('isolated counter fixture', PASSWORD_DEFAULT),
        'created_at' => gmdate(DATE_ATOM),
        'updated_at' => gmdate(DATE_ATOM),
        'auth_version' => 1,
        'status' => 'active',
        'deletion_requested_at' => null,
        'preferences' => AccountService::defaultPreferences(),
        'recovery_code_hashes' => [],
        'mutation_counter' => 0,
    ]), 'Could not seed the isolated concurrent update account.');
    $processes = [];
    for ($worker = 1; $worker <= 4; $worker++) {
        $command = [PHP_BINARY, dirname(__FILE__) . '/AccountConcurrencyWorker.php', $concurrentDirectory, (string) $worker, 'increment'];
        $process = proc_open($command, [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        accountAssert(is_resource($process), 'Could not start concurrent account-update worker.');
        $processes[] = [$process, $pipes];
    }
    foreach ($processes as [$process, $pipes]) {
        $output = trim((string) stream_get_contents($pipes[1]));
        $error = trim((string) stream_get_contents($pipes[2]));
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($process);
        $unexpectedError = preg_replace('/^WARNING: linker:.*(?:\r?\n|$)/m', '', $error) ?? $error;
        accountAssert($exit === 0 && $output === 'UPDATED' && trim($unexpectedError) === '', 'A concurrent account mutation worker failed.');
    }
    $counter = $concurrentRepository->findByUsername('concurrent-counter');
    accountAssert(($counter['mutation_counter'] ?? null) === 80, 'Concurrent account updates lost mutations.');

    $corruptDirectory = $fixture . '/corrupt';
    mkdir($corruptDirectory, 0700, true);
    $corruptPath = $corruptDirectory . '/accounts.json';
    $corruptState = '{"schema_version":1,"accounts":';
    file_put_contents($corruptPath, $corruptState);
    try {
        (new AccountRepository($corruptDirectory))->countAccounts();
        throw new RuntimeException('Corrupt account storage was accepted.');
    } catch (RuntimeException $exception) {
        accountAssert(str_contains($exception->getMessage(), 'corrupt'), 'Corrupt account storage did not fail with a safe diagnostic.');
    }
    accountAssert(file_get_contents($corruptPath) === $corruptState, 'Corruption recovery overwrote account data.');

    echo "[PASS] Hashed credentials/recovery, generic duplicate registration, unique immutable learner identities, private storage, session rotation, idle/absolute expiry, and rate limits.\n";
    echo "[PASS] Anonymous identity isolation, persistent cross-session preferences, two-account record/export isolation, recovery replay rejection, deletion lockout, password change, and stale/tampered session rejection.\n";
    echo "[PASS] Concurrent duplicate registration creates one unique account record; concurrent profile mutations lose no updates.\n";
    echo "[PASS] Corrupt account storage fails closed without overwriting the damaged source.\n";
    echo "[PASS] Unencrypted runtime backup excludes the private credential store; dedicated encrypted account backup is covered separately.\n";
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
    foreach (['APP_ENV','APP_DEBUG','BOARDPREP_ACCOUNTS_ENABLED','DB_DRIVER','APP_STORAGE_PATH','APP_SESSION_PATH'] as $key) {
        putenv($key);
        unset($_ENV[$key]);
    }
}
