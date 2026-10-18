<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

use App\Core\App;
use App\Services\Accounts\AccountDeletionService;
use App\Services\Accounts\AccountLifecycleLock;
use App\Services\Accounts\AccountService;

$mode = (string) ($argv[1] ?? '');
$accountId = (string) ($argv[2] ?? '');
$learnerId = (string) ($argv[3] ?? '');
if (!in_array($mode, ['delete', 'login', 'write', 'request-delete'], true)) {
    fwrite(STDERR, "Invalid synthetic race worker mode.\n");
    exit(2);
}

App::boot();

if ($mode === 'delete') {
    $announced = false;
    $result = (new AccountDeletionService(static function (string $collection) use (&$announced): void {
        if ($collection === 'quiz_attempts' && !$announced) {
            $announced = true;
            fwrite(STDOUT, "DELETION_LOCK_HELD\n");
            fflush(STDOUT);
            usleep(350_000);
        }
    }))->process($accountId);
    echo ($result['status'] ?? '') . "\n";
    exit(($result['status'] ?? '') === 'deletion_completed' ? 0 : 3);
}

if ($mode === 'login') {
    $username = fgets(STDIN);
    $password = fgets(STDIN);
    if (!is_string($username) || !is_string($password)) {
        fwrite(STDERR, "Synthetic login input was incomplete.\n");
        exit(4);
    }
    $signedIn = (new AccountService())->signIn(trim($username), rtrim($password, "\r\n"));
    echo $signedIn ? "LOGIN_ALLOWED\n" : "LOGIN_DENIED\n";
    exit($signedIn ? 5 : 0);
}

if ($mode === 'request-delete') {
    $username = fgets(STDIN);
    $password = fgets(STDIN);
    if (!is_string($username) || !is_string($password)) {
        fwrite(STDERR, "Synthetic deletion request input was incomplete.\n");
        exit(7);
    }
    fwrite(STDOUT, "DELETION_REQUEST_ATTEMPTING\n");
    fflush(STDOUT);
    $lockPath = (new \App\Services\Accounts\AccountRepository())->directoryPath() . '/lifecycle.lock';
    $lock = fopen($lockPath, 'c+b');
    if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
        if (is_resource($lock)) fclose($lock);
        fwrite(STDOUT, "DELETION_REQUEST_BLOCKED_BY_LIFECYCLE_LOCK\n");
        fflush(STDOUT);
    } else {
        flock($lock, LOCK_UN);
        fclose($lock);
        fwrite(STDOUT, "DELETION_REQUEST_LOCK_AVAILABLE\n");
        fflush(STDOUT);
    }
    $service = new AccountService();
    if (!$service->signIn(trim($username), rtrim($password, "\r\n"))) {
        fwrite(STDOUT, "LOGIN_DENIED\n");
        exit(0);
    }
    if (!$service->requestDeletion(rtrim($password, "\r\n"))) {
        fwrite(STDOUT, "DELETION_REQUEST_DENIED\n");
        exit(8);
    }
    fwrite(STDOUT, "DELETION_REQUESTED\n");
    exit(0);
}

try {
    AccountLifecycleLock::withWritableLearner($learnerId, static function () use ($learnerId): void {
        App::storage()->create('attempts', [
            'id' => 'batch693-race-late-write',
            'user_id' => $learnerId,
            'score' => 1,
            'total' => 1,
        ]);
    });
    echo "WRITE_ALLOWED\n";
    exit(6);
} catch (RuntimeException) {
    echo "WRITE_DENIED\n";
    exit(0);
}
