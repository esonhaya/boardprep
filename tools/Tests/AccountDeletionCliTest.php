<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

use App\Core\App;
use App\Services\Accounts\AccountRepository;

function deletionCliAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

/** @return array{int,string,string} */
function deletionCliRun(array $arguments, string $root, array $environment): array
{
    $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $process = proc_open([PHP_BINARY, $root . '/tools/account-delete.php', ...$arguments], $descriptors, $pipes, $root, $environment);
    if (!is_resource($process)) {
        throw new RuntimeException('Could not start the isolated deletion CLI fixture.');
    }
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    return [proc_close($process), is_string($stdout) ? $stdout : '', is_string($stderr) ? $stderr : ''];
}

$root = dirname(__DIR__, 2);
$fixture = sys_get_temp_dir() . '/boardprep-account-deletion-cli-' . bin2hex(random_bytes(8));
$storage = $fixture . '/storage';
$sessions = $fixture . '/sessions';
mkdir($storage, 0700, true);
mkdir($sessions, 0700, true);
$accountId = 'account-' . bin2hex(random_bytes(16));
$learnerId = 'account-learner-' . bin2hex(random_bytes(16));
$username = 'synthetic-delete-cli';
$passwordHash = password_hash('Synthetic CLI fixture password phrase', PASSWORD_DEFAULT);
$recoveryHash = hash('sha256', 'synthetic-recovery-fixture');
$environment = [
    'PATH' => (string) getenv('PATH'),
    'APP_ENV' => 'testing',
    'APP_DEBUG' => 'false',
    'BOARDPREP_ACCOUNTS_ENABLED' => 'true',
    'DB_DRIVER' => 'json',
    'APP_STORAGE_PATH' => $storage,
    'DB_SQLITE_PATH' => $fixture . '/runtime.sqlite',
    'APP_SESSION_PATH' => $sessions,
];

try {
    foreach ($environment as $key => $value) {
        putenv($key . '=' . $value);
        $_ENV[$key] = $value;
    }
    App::boot();
    $repository = new AccountRepository();
    deletionCliAssert($repository->create([
        'account_id' => $accountId,
        'learner_id' => $learnerId,
        'username' => $username,
        'password_hash' => $passwordHash,
        'created_at' => gmdate(DATE_ATOM),
        'updated_at' => gmdate(DATE_ATOM),
        'auth_version' => 2,
        'status' => 'deletion_failed',
        'deletion_requested_at' => gmdate(DATE_ATOM),
        'deletion_journal' => ['schema_version' => 1, 'completed_collections' => []],
        'preferences' => ['preferred_board' => 'synthetic'],
        'recovery_code_hashes' => [$recoveryHash],
    ]), 'Synthetic pending account could not be created.');
    App::storage()->create('attempts', ['id' => 'synthetic-delete-cli-attempt', 'user_id' => $learnerId, 'score' => 1]);
    App::storage()->create('weakness', ['id' => 'synthetic-delete-cli-weakness', 'user_id' => $learnerId, 'topic' => 'synthetic']);
    App::storage()->create('flashcard_state', ['id' => 'synthetic-delete-cli-card', 'user_id' => $learnerId, 'card_key' => 'synthetic', 'review_count' => 1]);

    [$status, $stdout, $stderr] = deletionCliRun(['--list-pending'], $root, $environment);
    deletionCliAssert($status === 0 && $stderr === '', 'Pending deletion inventory command failed.');
    deletionCliAssert(str_contains($stdout, $accountId) && str_contains($stdout, 'deletion_failed'), 'Pending deletion inventory omitted its recovery target.');
    foreach ([$username, (string) $passwordHash, $recoveryHash, $learnerId] as $secret) {
        deletionCliAssert(!str_contains($stdout, $secret), 'Deletion CLI inventory exposed credentials or learner identifiers.');
    }

    [$status, , $stderr] = deletionCliRun(['--account-id=' . $accountId, '--confirm-account-id=account-' . str_repeat('0', 32)], $root, $environment);
    deletionCliAssert($status !== 0 && str_contains($stderr, 'CLI_ARGUMENT_INVALID'), 'A mismatched operator confirmation was accepted.');
    deletionCliAssert(($repository->findById($accountId)['status'] ?? null) === 'deletion_failed', 'Invalid confirmation changed the deletion state.');
    deletionCliAssert(count(App::storage()->where('attempts', ['user_id' => $learnerId])) === 1, 'Invalid confirmation deleted synthetic learner data.');

    [$status, $stdout, $stderr] = deletionCliRun([
        '--account-id=' . $accountId,
        '--confirm-account-id=' . $accountId,
    ], $root, $environment);
    deletionCliAssert($status === 0 && $stderr === '', 'Confirmed retry of a previously requested deletion failed: ' . trim($stderr));
    $result = json_decode($stdout, true, 32, JSON_THROW_ON_ERROR);
    deletionCliAssert(($result['status'] ?? null) === 'DELETION_COMPLETED', 'Deletion CLI did not report verified completion.');
    deletionCliAssert(($result['deleted_record_counts']['quiz_attempts'] ?? null) === 1, 'Deletion CLI report omitted owned quiz data.');
    deletionCliAssert(($repository->findById($accountId)['status'] ?? null) === 'deletion_completed', 'Deletion CLI did not leave an idempotent tombstone.');
    foreach (['attempts', 'weakness', 'flashcard_state'] as $collection) {
        deletionCliAssert(App::storage()->where($collection, ['user_id' => $learnerId]) === [], 'Deletion CLI left a synthetic owned record behind.');
    }

    [$status, $stdout, $stderr] = deletionCliRun([
        '--account-id=' . $accountId,
        '--confirm-account-id=' . $accountId,
    ], $root, $environment);
    $result = json_decode($stdout, true, 32, JSON_THROW_ON_ERROR);
    deletionCliAssert($status === 0 && $stderr === '' && ($result['already_completed'] ?? false) === true, 'Repeated CLI deletion was not idempotent.');
    echo "[PASS] Nonproduction deletion CLI lists only pseudonymous targets, rejects mismatched confirmation, resumes a failed journal, verifies cleanup, and retries idempotently on synthetic fixture data.\n";
} finally {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($fixture, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $entry) {
        if ($entry->isDir() && !$entry->isLink()) @rmdir($entry->getPathname());
        else @unlink($entry->getPathname());
    }
    @rmdir($fixture);
    foreach (array_keys($environment) as $key) {
        putenv($key);
        unset($_ENV[$key]);
    }
}
