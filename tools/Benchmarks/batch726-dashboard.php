<?php

declare(strict_types=1);

// Compare empty and returning-learner dashboard request latency on an isolated
// copy of repository content. No repository learner collection is modified.
putenv('APP_ENV=testing');
putenv('APP_DEBUG=false');
putenv('DB_DRIVER=json');
putenv('APP_STORAGE_PATH');
putenv('APP_SESSION_PATH');

require_once dirname(__DIR__) . '/Doctor/Project/BoardPrep/Simulation/HttpSimulator.php';
require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

use Tools\Doctor\Project\BoardPrep\Simulation\HttpSimulator;

$root = dirname(__DIR__, 2);
$http = new HttpSimulator($root . '/public/index.php');
$storagePath = $http->storagePath();
$sessionPath = $http->sessionPath();
$cookieId = 'batch726-dashboard-bench-' . bin2hex(random_bytes(8));
$cookies = ['PHPSESSID' => $cookieId];

$readSession = static function () use ($cookieId, $sessionPath): array {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    ini_set('session.use_cookies', '0');
    ini_set('session.use_strict_mode', '0');
    session_save_path($sessionPath);
    session_id($cookieId);
    if (!session_start()) {
        throw new RuntimeException('Unable to read the isolated benchmark session.');
    }
    $session = $_SESSION;
    session_write_close();
    return is_array($session) ? $session : [];
};

$measure = static function (int $count) use ($http, $cookies): array {
    $samples = [];
    for ($i = 0; $i < $count; $i++) {
        $response = $http->request('GET', '/dashboard', [], [], [], $cookies);
        if (!$response['success'] || $response['status'] !== 200) {
            throw new RuntimeException('Dashboard benchmark request failed with HTTP ' . $response['status']);
        }
        $samples[] = round((float) $response['duration'] * 1000, 3);
    }
    sort($samples, SORT_NUMERIC);
    return [
        'samples_ms' => $samples,
        'median_ms' => $samples === [] ? null : $samples[intdiv(count($samples), 2)],
        'min_ms' => $samples[0] ?? null,
        'max_ms' => $samples === [] ? null : $samples[array_key_last($samples)],
    ];
};

$empty = $measure(1);
$session = $readSession();
$learnerId = is_string($session['learner_identity'] ?? null) ? $session['learner_identity'] : '';
if ($learnerId === '') {
    throw new RuntimeException('Dashboard benchmark did not establish an isolated learner identity.');
}

$attempts = [];
$now = time();
for ($i = 0; $i < 100; $i++) {
    $attempts[] = [
        'id' => 'benchmark-attempt-' . str_pad((string) $i, 4, '0', STR_PAD_LEFT),
        'session_id' => 'benchmark-session-' . str_pad((string) $i, 4, '0', STR_PAD_LEFT),
        'user_id' => $learnerId,
        'completed' => true,
        'mode' => 'practice',
        'board' => 'let',
        'subject' => 'English',
        'topic' => 'batch726-benchmark-topic',
        'score' => 7,
        'total' => 10,
        'answered' => 10,
        'incorrect' => 3,
        'unanswered' => 0,
        'percentage' => 70,
        'completed_at' => gmdate('Y-m-d\TH:i:s\Z', $now - (($i + 1) * 60)),
        'review' => [],
    ];
}
$attemptsJson = json_encode($attempts, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
if (file_put_contents($storagePath . '/attempts.json', $attemptsJson, LOCK_EX) === false) {
    throw new RuntimeException('Unable to seed isolated dashboard benchmark history.');
}

$returningCold = $measure(1);
$returningWarm = $measure(7);
echo json_encode([
    'workload' => [
        'learner_attempts' => 100,
        'mode' => 'practice',
        'history_review_payload' => 'empty; all attempts synthetic and isolated',
        'canonical_bank_mutations' => 0,
        'learner_storage' => 'temporary simulation fixture only',
    ],
    'empty_dashboard_cold' => $empty,
    'returning_dashboard_cold' => $returningCold,
    'returning_dashboard_warm' => $returningWarm,
    'process_peak_memory' => 'NOT_MEASURED (HTTP simulator reports request latency, not child PHP RSS)',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
