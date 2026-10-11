<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Doctor/Project/BoardPrep/Simulation/HttpSimulator.php';
require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

use App\Core\Database;
use App\Services\Privacy\AnonymousLearnerDataService;
use App\Storage\JsonStorage;
use Tools\Doctor\Project\BoardPrep\Simulation\HttpSimulator;

$root = dirname(__DIR__, 2);
$storagePath = sys_get_temp_dir() . '/boardprep-privacy-' . bin2hex(random_bytes(6));
$sessionDirectory = $root . '/storage/doctor/simulation-sessions';
$existingSessions = array_fill_keys(glob($sessionDirectory . '/sess-*') ?: [], true);
$sessionIds = [
    'batch663privacy' . bin2hex(random_bytes(6)),
    'batch663privacy' . bin2hex(random_bytes(6)),
];
$learnerIds = [
    'learner-' . str_repeat('a', 32),
    'learner-' . str_repeat('b', 32),
];

if (!mkdir($storagePath, 0700, true)) {
    throw new RuntimeException('Unable to create isolated privacy fixture.');
}
if (!is_dir($sessionDirectory) && !mkdir($sessionDirectory, 0700, true) && !is_dir($sessionDirectory)) {
    throw new RuntimeException('Unable to initialize privacy test session directory.');
}

putenv('APP_ENV=production');
putenv('APP_DEBUG=false');
putenv('APP_TIMEZONE=UTC');
putenv('DB_DRIVER=json');
putenv('APP_STORAGE_PATH=' . $storagePath);
putenv('PRIVACY_OPERATOR_NAME=');
putenv('PRIVACY_OPERATOR_CONTACT=');
putenv('PRIVACY_RETENTION_NOTICE=');
putenv('PRIVACY_HOSTING_NOTICE=');
putenv('PRIVACY_TERMS_APPROVED=false');

$storage = new JsonStorage($storagePath);
foreach ([
    ['attempts', 'attempt-a', 'quiz_attempts'],
    ['weakness', 'weakness-a', 'weakness_summaries'],
    ['flashcard_state', 'flashcard-a', 'flashcard_reviews'],
    ['attempts', 'attempt-b', 'quiz_attempts'],
    ['weakness', 'weakness-b', 'weakness_summaries'],
    ['flashcard_state', 'flashcard-b', 'flashcard_reviews'],
] as [$collection, $id, $recordType]) {
    $owner = str_ends_with($id, '-a') ? $learnerIds[0] : $learnerIds[1];
    $storage->create($collection, [
        'id' => $id,
        'user_id' => $owner,
        'session_id' => 'private-' . $owner,
        'question_id' => 'sample-question',
        'record_type' => $recordType,
    ]);
}
$storage->create('question-statistics', ['id' => 'aggregate-question', 'timesUsed' => 5]);

$seedSession = static function (string $sessionId, string $learnerId) use ($sessionDirectory): void {
    ini_set('session.use_cookies', '0');
    ini_set('session.cache_limiter', '');
    session_save_path($sessionDirectory);
    session_id($sessionId);
    if (!session_start()) {
        throw new RuntimeException('Unable to seed isolated learner session.');
    }
    $_SESSION = ['learner_identity' => $learnerId];
    session_write_close();
};

$http = new HttpSimulator($root . '/public/index.php');
$sessionCookies = ['PHPSESSID' => $sessionIds[0]];

try {
    $seedSession($sessionIds[0], $learnerIds[0]);
    $seedSession($sessionIds[1], $learnerIds[1]);

    $page = $http->request('GET', '/privacy', [], [], [], $sessionCookies);
    if (!$page['success'] || $page['status'] !== 200
        || !str_contains($page['output'], 'quiz_attempts: 1')
        || !str_contains($page['output'], 'Clear my learning data')
        || !str_contains($page['output'], 'Public launch must wait')) {
        throw new RuntimeException('Privacy page did not show session-scoped records and incomplete owner terms.');
    }
    if (!preg_match('/name="csrf_token" value="([a-f0-9]{64})"/', $page['output'], $csrfMatch)) {
        throw new RuntimeException('Privacy clear form did not render a CSRF token.');
    }
    $csrfToken = $csrfMatch[1];

    $export = $http->request('GET', '/privacy/export', [], [], [], $sessionCookies);
    $exportData = json_decode($export['output'], true);
    $exportText = $export['output'];
    if (!$export['success'] || $export['status'] !== 200
        || !is_array($exportData)
        || count((array) ($exportData['records']['quiz_attempts'] ?? [])) !== 1
        || str_contains($exportText, $learnerIds[0])
        || str_contains($exportText, $learnerIds[1])
        || str_contains($exportText, 'private-learner-')) {
        throw new RuntimeException('Learner export exposed another learner or session identity.');
    }

    $invalidCsrf = $http->request('POST', '/privacy/data/clear', [], [
        'csrf_token' => str_repeat('0', 64),
        'confirm_clear' => 'yes',
    ], [], $sessionCookies);
    if ($invalidCsrf['status'] !== 303 || count($storage->all('attempts')) !== 2) {
        throw new RuntimeException('Privacy deletion did not reject invalid CSRF without changing data.');
    }
    $missingConfirmation = $http->request('POST', '/privacy/data/clear', [], [
        'csrf_token' => $csrfToken,
    ], [], $sessionCookies);
    if ($missingConfirmation['status'] !== 303 || count($storage->all('attempts')) !== 2) {
        throw new RuntimeException('Privacy deletion did not require explicit confirmation.');
    }

    $cleared = $http->request('POST', '/privacy/data/clear', [], [
        'csrf_token' => $csrfToken,
        'confirm_clear' => 'yes',
    ], [], $sessionCookies);
    if (!$cleared['success'] || $cleared['status'] !== 303
        || is_file($sessionDirectory . '/sess-' . $sessionIds[0])) {
        throw new RuntimeException('Confirmed privacy deletion did not clear and rotate the browser session.');
    }
    foreach (['attempts', 'weakness', 'flashcard_state'] as $collection) {
        $remaining = $storage->all($collection);
        if (count($remaining) !== 1 || ($remaining[0]['user_id'] ?? '') !== $learnerIds[1]) {
            throw new RuntimeException('Privacy deletion removed another learner or left owned data in ' . $collection . '.');
        }
    }
    if (count($storage->all('question-statistics')) !== 1) {
        throw new RuntimeException('Privacy deletion changed aggregate, non-learner question statistics.');
    }

    putenv('PRIVACY_OPERATOR_NAME=<script>alert(1)</script>');
    putenv('PRIVACY_OPERATOR_CONTACT=privacy@example.test');
    putenv('PRIVACY_RETENTION_NOTICE=Owner-approved fixture retention text.');
    putenv('PRIVACY_HOSTING_NOTICE=Fixture-only hosting and backup notice.');
    putenv('PRIVACY_TERMS_APPROVED=true');
    $configuredPolicy = $http->request('GET', '/privacy', [], [], [], $sessionCookies);
    if (!$configuredPolicy['success'] || !str_contains($configuredPolicy['output'], 'Site operator\'s privacy terms')
        || !str_contains($configuredPolicy['output'], '&lt;script&gt;alert(1)&lt;/script&gt;')
        || str_contains($configuredPolicy['output'], '<script>alert(1)</script>')) {
        throw new RuntimeException('Configured privacy terms were not rendered safely with HTML escaping.');
    }

    foreach (['json', 'sqlite'] as $driver) {
        $driverPath = $storagePath . '/' . $driver;
        if (!mkdir($driverPath, 0700, true)) {
            throw new RuntimeException('Unable to create ' . $driver . ' privacy fixture.');
        }
        if ($driver === 'json') {
            $driverStorage = new JsonStorage($driverPath);
            $database = new Database(['driver' => 'json', 'path' => $driverPath]);
        } else {
            if (!extension_loaded('pdo_sqlite')) {
                echo "[NOT_VERIFIED] SQLite privacy parity: pdo_sqlite is unavailable.\n";
                continue;
            }
            $database = new Database([
                'driver' => 'sqlite',
                'sqlite_path' => $driverPath . '/learner.sqlite',
                'path' => $driverPath . '/json',
                'migration_path' => $root . '/database/migrations',
            ]);
            $driverStorage = $database->storage();
        }
        foreach ([['one', $learnerIds[0]], ['two', $learnerIds[1]]] as [$suffix, $owner]) {
            foreach (['attempts', 'weakness', 'flashcard_state'] as $collection) {
                $driverStorage->create($collection, [
                    'id' => $collection . '-' . $suffix,
                    'user_id' => $owner,
                ]);
            }
        }
        $service = new AnonymousLearnerDataService($driverStorage, $database, $learnerIds[0]);
        $service->clear();
        $service->clear();
        foreach (['attempts', 'weakness', 'flashcard_state'] as $collection) {
            $remaining = $driverStorage->all($collection);
            if (count($remaining) !== 1 || ($remaining[0]['user_id'] ?? '') !== $learnerIds[1]) {
                throw new RuntimeException($driver . ' deletion parity failed for ' . $collection . '.');
            }
        }
    }

    echo "[PASS] Production privacy page, owner-term escaping, scoped export, CSRF, confirmation, session rotation, and learner isolation.\n";
    echo "[PASS] Idempotent JSON and SQLite deletion preserve other learner and aggregate state.\n";
} finally {
    foreach (glob($sessionDirectory . '/sess-*') ?: [] as $sessionFile) {
        if (!isset($existingSessions[$sessionFile])) {
            @unlink($sessionFile);
        }
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($storagePath, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $entry) {
        if ($entry->isDir()) {
            @rmdir($entry->getPathname());
        } else {
            @unlink($entry->getPathname());
        }
    }
    @rmdir($storagePath);
    foreach ([
        'APP_ENV', 'APP_DEBUG', 'APP_TIMEZONE', 'DB_DRIVER', 'APP_STORAGE_PATH',
        'PRIVACY_OPERATOR_NAME', 'PRIVACY_OPERATOR_CONTACT', 'PRIVACY_RETENTION_NOTICE',
        'PRIVACY_HOSTING_NOTICE', 'PRIVACY_TERMS_APPROVED',
    ] as $environmentKey) {
        putenv($environmentKey);
    }
}
