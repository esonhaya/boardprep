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

$sessionId = 'batch676-production-csrf-' . bin2hex(random_bytes(8));
$cookies = ['PHPSESSID' => $sessionId];
$http = new HttpSimulator($root . '/public/index.php');

try {
    $settings = $http->request('GET', '/quiz', [], [], [], $cookies);
    if (!$settings['success'] || !preg_match('/name="csrf_token" value="([^"]+)"/', $settings['output'], $match)) {
        throw new RuntimeException('production quiz settings did not render a CSRF token');
    }

    $profile = $http->request('GET', '/profile', [], [], [], $cookies);
    if (!$profile['success'] || !preg_match('/name="csrf_token" value="([^"]+)"/', $profile['output'], $profileMatch)) {
        throw new RuntimeException('production learner profile did not render its preference CSRF token');
    }
    $profilePayload = [
        'preferred_board' => 'CSE',
        'preferred_subject' => 'general-information',
        'civil_service_level' => 'PROFESSIONAL',
    ];
    $profileMissingCsrf = $http->request('POST', '/profile/preferences', [], $profilePayload, [], $cookies);
    if ($profileMissingCsrf['status'] !== 303 || $profileMissingCsrf['location'] !== '/profile') {
        throw new RuntimeException('production learner preferences accepted a POST without CSRF protection');
    }
    $profileValidCsrf = $http->request('POST', '/profile/preferences', [], $profilePayload + ['csrf_token' => $profileMatch[1]], [], $cookies);
    if ($profileValidCsrf['status'] !== 303 || $profileValidCsrf['location'] !== '/profile') {
        throw new RuntimeException('production learner preferences rejected a valid CSRF-protected update');
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

    $invalidTimedPayload = [
        'action' => 'start',
        'exam' => 'LET',
        'subject' => 'english',
        'mode' => 'exam',
        'timer_mode' => 'timed',
        'time_limit_minutes' => '2.5',
        'count' => 5,
        'csrf_token' => $match[1],
    ];
    $invalidTimed = $http->request('POST', '/quiz', [], $invalidTimedPayload, [], $cookies);
    if ($invalidTimed['status'] !== 302 || !is_string($invalidTimed['location'])
        || !str_contains($invalidTimed['location'], 'timer_mode=timed')
        || !str_contains($invalidTimed['location'], 'time_limit_minutes=2.5')
        || !str_contains($invalidTimed['location'], 'subject=english')) {
        throw new RuntimeException('invalid timed duration did not preserve its board, subject, and entered value');
    }
    parse_str((string) parse_url($invalidTimed['location'], PHP_URL_QUERY), $invalidTimerQuery);
    $invalidTimerSettings = $http->request('GET', '/quiz', $invalidTimerQuery, [], [], $cookies);
    if (!$invalidTimerSettings['success']
        || !str_contains($invalidTimerSettings['output'], 'value="2.5"')
        || !str_contains($invalidTimerSettings['output'], 'Choose a whole-number timer from 1 to 600 minutes.')) {
        throw new RuntimeException('invalid timed duration did not return to a usable form with its error and value');
    }

    foreach (['/developer', '/developer/doctor', '/developer/question-origin?id=1', '/question-editor'] as $protectedPath) {
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

    echo "[PASS] production quiz and preference mutations require CSRF; developer/admin routes remain unavailable.\n";
} finally {
    $sessionDirectory = trim((string) (getenv('APP_SESSION_PATH') ?: ''));
    if ($sessionDirectory === '') {
        $sessionDirectory = $root . '/storage/doctor/simulation-sessions';
    }
    $sessionPath = rtrim($sessionDirectory, DIRECTORY_SEPARATOR) . '/sess_' . $sessionId;
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
