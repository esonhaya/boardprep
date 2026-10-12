<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Doctor/Project/BoardPrep/Simulation/HttpSimulator.php';
require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

use Tools\Doctor\Project\BoardPrep\Simulation\HttpSimulator;

$root = dirname(__DIR__, 2);
$storage = sys_get_temp_dir() . '/boardprep-csrf-' . bin2hex(random_bytes(6));
mkdir($storage, 0777, true);
copy($root . '/storage/questions.json', $storage . '/questions.json');

putenv('APP_ENV=production');
putenv('APP_DEBUG=false');
putenv('APP_TIMEZONE=UTC');
putenv('DB_DRIVER=json');
putenv('APP_STORAGE_PATH=' . $storage);

$sessionId = 'batch556-production-csrf';
$cookies = ['PHPSESSID' => $sessionId];
$http = new HttpSimulator($root . '/public/index.php');

try {
    $settings = $http->request('GET', '/quiz', [], [], [], $cookies);
    if (!$settings['success'] || !preg_match('/name="csrf_token" value="([^"]+)"/', $settings['output'], $match)) {
        throw new RuntimeException('production quiz settings did not render a CSRF token');
    }

    $payload = [
        'action' => 'start',
        'exam' => 'LET',
        'subject' => 'English',
        'mode' => 'practice',
        'difficulty' => 'mixed',
        'count' => 1,
    ];
    $missing = $http->request('POST', '/quiz', [], $payload, [], $cookies);
    if (!$missing['success'] || $missing['status'] !== 302 || $missing['location'] !== '/quiz') {
        throw new RuntimeException('production quiz accepted a POST without CSRF protection');
    }

    $valid = $http->request('POST', '/quiz', [], $payload + ['csrf_token' => $match[1]], [], $cookies);
    if (!$valid['success'] || !str_contains($valid['output'], 'Question 1 /')) {
        throw new RuntimeException('production quiz rejected a valid CSRF-protected start');
    }

    foreach (['/developer', '/developer/doctor', '/question-editor'] as $protectedPath) {
        $protected = $http->request('GET', $protectedPath, [], [], [], $cookies);
        if ($protected['status'] !== 404) {
            throw new RuntimeException('production exposed a developer/admin route: ' . $protectedPath);
        }
    }
    $doctorApi = new HttpSimulator($root . '/public/api/doctor.php');
    $apiResponse = $doctorApi->request('GET', '/api/doctor.php', [], [], [], $cookies);
    if ($apiResponse['status'] !== 404) {
        throw new RuntimeException('production exposed the Doctor API.');
    }

    echo "[PASS] production quiz mutations require CSRF and developer/admin routes remain unavailable.\n";
} finally {
    $sessionDirectory = trim((string) (getenv('APP_SESSION_PATH') ?: ''));
    if ($sessionDirectory === '') {
        $sessionDirectory = $root . '/storage/doctor/simulation-sessions';
    }
    $sessionPath = rtrim($sessionDirectory, DIRECTORY_SEPARATOR) . '/sess-' . $sessionId;
    @unlink($sessionPath);
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($storage, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $file) {
        if ($file->isDir()) {
            @rmdir($file->getPathname());
        } else {
            @unlink($file->getPathname());
        }
    }
    @rmdir($storage);
}
