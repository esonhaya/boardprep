<?php

declare(strict_types=1);

// Comparable dashboard request benchmark for isolated synthetic learner histories.
// Each workload is measured after replacing only the simulator's attempts.json.
putenv('APP_ENV=testing');
putenv('APP_DEBUG=false');
putenv('DB_DRIVER=json');
putenv('APP_STORAGE_PATH');
putenv('APP_SESSION_PATH');

require_once dirname(__DIR__) . '/Doctor/Project/BoardPrep/Simulation/HttpSimulator.php';
require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

use Tools\Doctor\Project\BoardPrep\Simulation\HttpSimulator;

$http = new HttpSimulator(dirname(__DIR__, 2) . '/public/index.php');
$storagePath = $http->storagePath();
$sessionPath = $http->sessionPath();
$cookieId = 'batch727-dashboard-' . bin2hex(random_bytes(8));
$cookies = ['PHPSESSID' => $cookieId];

$response = $http->request('GET', '/dashboard', [], [], [], $cookies);
if (!$response['success'] || $response['status'] !== 200) {
    throw new RuntimeException('Unable to initialize isolated dashboard learner.');
}

$readLearnerId = static function () use ($cookieId, $sessionPath): string {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    ini_set('session.use_cookies', '0');
    ini_set('session.use_strict_mode', '0');
    session_save_path($sessionPath);
    session_id($cookieId);
    if (!session_start()) {
        throw new RuntimeException('Unable to inspect isolated benchmark session.');
    }
    $id = $_SESSION['learner_identity'] ?? '';
    session_write_close();
    return is_string($id) ? $id : '';
};

$learnerId = $readLearnerId();
if ($learnerId === '') {
    throw new RuntimeException('Simulator did not assign a learner identity.');
}

$measure = static function (int $count) use ($http, $cookies): array {
    $sample = static function () use ($http, $cookies): float {
        $result = $http->request('GET', '/dashboard', [], [], [], $cookies);
        if (!$result['success'] || $result['status'] !== 200) {
            throw new RuntimeException('Dashboard request failed with HTTP ' . $result['status']);
        }
        return round((float) $result['duration'] * 1000, 3);
    };
    $cold = $sample();
    $warm = [];
    for ($i = 0; $i < $count; $i++) {
        $warm[] = $sample();
    }
    sort($warm, SORT_NUMERIC);
    $middle = intdiv(count($warm), 2);
    $median = $warm === [] ? null : (count($warm) % 2 === 1
        ? $warm[$middle]
        : round(($warm[$middle - 1] + $warm[$middle]) / 2, 3));
    return [
        'first_request_ms' => $cold,
        'warm_samples_ms' => $warm,
        'warm_median_ms' => $median,
        'warm_min_ms' => $warm[0] ?? null,
        'warm_max_ms' => $warm === [] ? null : $warm[array_key_last($warm)],
    ];
};

$workloads = [];
$now = time();
foreach ([0, 1, 25, 100, 500] as $count) {
    $attempts = [];
    for ($i = 0; $i < $count; $i++) {
        $attempts[] = [
            'id' => 'b727-benchmark-attempt-' . $i,
            'session_id' => 'b727-benchmark-session-' . $i,
            'user_id' => $learnerId,
            'completed' => true,
            'mode' => 'practice',
            'board' => 'let',
            'subject' => 'English',
            'topic' => 'b727-benchmark-topic',
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
    $encoded = json_encode($attempts, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
    if (file_put_contents($storagePath . '/attempts.json', $encoded, LOCK_EX) === false) {
        throw new RuntimeException('Unable to seed isolated dashboard workload.');
    }
    $workloads[(string) $count] = $measure(7);
}

echo json_encode([
    'benchmark' => 'batch727-dashboard-http',
    'runtime' => PHP_OS_FAMILY . '/' . PHP_SAPI . ' via isolated HTTP simulator',
    'workloads' => $workloads,
    'fixture' => [
        'attempt_counts' => [0, 1, 25, 100, 500],
        'attempt_shape' => 'completed synthetic practice attempts; empty review payload',
        'learner' => 'ephemeral simulator identity',
        'repository_learner_writes' => 0,
    ],
    'instrumentation' => [
        'peak_child_php_memory' => 'NOT_MEASURED',
        'storage_read_count' => 'NOT_INSTRUMENTED',
        'canonical_bank_read_count' => 'NOT_INSTRUMENTED',
        'cold_definition' => 'first request after replacing simulator fixture; OS page cache is not cleared',
        'warm_samples_per_workload' => 7,
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
