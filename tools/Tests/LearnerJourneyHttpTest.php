<?php

declare(strict_types=1);

require_once dirname(__DIR__) . "/Doctor/Project/BoardPrep/Simulation/HttpSimulator.php";
require_once dirname(__DIR__, 2) . "/app/Core/Autoloader.php";
\App\Core\Autoloader::register();

use App\Core\App;
use App\Repositories\AttemptRepository;
use App\Services\Learning\WeaknessService;
use App\Services\Learning\WeaknessStorageService;
use Tools\Doctor\Project\BoardPrep\Simulation\HttpSimulator;

$root = dirname(__DIR__, 2);
$attemptsPath = $root . '/storage/attempts.json';
$questionsPath = $root . '/storage/questions.json';
$questionsBefore = (string) file_get_contents($questionsPath);
$before = json_decode((string) file_get_contents($attemptsPath), true);
$before = is_array($before) ? $before : [];
$beforeIds = array_values(array_filter(array_map(
    static fn(mixed $row): ?string => is_array($row) && is_scalar($row['id'] ?? null) ? (string) $row['id'] : null,
    $before
)));
$beforeWeaknesses = WeaknessStorageService::all();
$http = new HttpSimulator($root . '/public/index.php');
$cookies = ['PHPSESSID' => 'batch436-learner-journey'];

$complete = static function (array $query) use ($http, $cookies): void {
    $page = $http->request('GET', '/quiz', $query, [], [], $cookies);
    if (!$page['success'] || !preg_match('/Question 1 \/ (\d+)/', $page['output'], $totalMatch)) {
        throw new RuntimeException('targeted quiz did not start through normalized production path');
    }
    $total = (int) $totalMatch[1];
    for ($index = 0; $index < $total; $index++) {
        if (!preg_match('/name="question_id"\s+value="([^"]+)"/', $page['output'], $id)) {
            throw new RuntimeException('quiz question id was not rendered');
        }
        $submit = $http->request('POST', '/quiz', ['action' => 'submit'], [
            'question_id' => $id[1],
            'answer' => '',
        ], [], $cookies);
        if (!$submit['success']) {
            throw new RuntimeException('quiz answer submission failed');
        }
        if ($index + 1 < $total) {
            $page = $http->request('GET', '/quiz', ['action' => 'next'], [], [], $cookies);
        }
    }
    $result = $http->request('GET', '/quiz', ['action' => 'finish'], [], [], $cookies);
    if (!$result['success'] || !str_contains($result['output'], 'Quiz Result')) {
        throw new RuntimeException('quiz completion did not render');
    }
};

try {
    foreach (['/dashboard', '/history', '/profile', '/progress', '/study'] as $path) {
        $empty = $http->request('GET', $path, [], [], [], $cookies);
        if (!$empty['success']) {
            throw new RuntimeException("new learner page failed: {$path}");
        }
    }

    $complete([
        'action' => 'start', 'subject' => 'English', 'topic' => 'Subject-Verb Agreement',
        'mode' => 'practice', 'difficulty' => 'mixed', 'count' => 1,
    ]);

    $outputs = [];
    foreach (['/dashboard', '/history', '/profile', '/progress', '/study'] as $path) {
        $response = $http->request('GET', $path, [], [], [], $cookies);
        if (!$response['success'] || !str_contains($response['output'], 'Subject-Verb Agreement')) {
            throw new RuntimeException("first completion is inconsistent on {$path}");
        }
        $outputs[$path] = $response['output'];
    }

    if (!preg_match_all('/href="(\/quiz\?action=start&amp;[^\"]+)"/', $outputs['/study'], $links)) {
        throw new RuntimeException('study recommendation has no targeted quiz action');
    }
    $url = '';
    foreach ($links[1] as $candidate) {
        $decoded = html_entity_decode($candidate, ENT_QUOTES | ENT_HTML5);
        if (str_contains($decoded, 'subject=English')
            && str_contains($decoded, 'topic=Subject-Verb')) {
            $url = $decoded;
            break;
        }
    }
    if ($url === '') {
        throw new RuntimeException('study recommendation lost the completed learner topic');
    }
    parse_str((string) parse_url($url, PHP_URL_QUERY), $target);
    foreach (['subject', 'topic', 'mode', 'difficulty', 'count'] as $field) {
        if (!array_key_exists($field, $target)) {
            throw new RuntimeException("rendered targeted action lost {$field}");
        }
    }
    $complete($target);

    $after = json_decode((string) file_get_contents($attemptsPath), true);
    $new = array_values(array_filter(is_array($after) ? $after : [], static fn(mixed $row): bool =>
        is_array($row) && is_scalar($row['id'] ?? null) && !in_array((string) $row['id'], $beforeIds, true)
    ));
    if (count($new) !== 2) {
        throw new RuntimeException('learner journey did not persist exactly two attempts');
    }
    foreach (['/dashboard', '/history', '/profile', '/progress', '/study'] as $path) {
        $updated = $http->request('GET', $path, [], [], [], $cookies);
        if (!$updated['success'] || !preg_match('/(Completed Quizzes|Total Quizzes):\s*(?:<strong>)?2/', $updated['output'])) {
            throw new RuntimeException("second completion is stale on {$path}");
        }
    }
    echo "[PASS] learner HTTP journey launches a rendered targeted action and persists exactly once.\n";
    echo "[PASS] dashboard, history, profile, progress, and study refresh after both completions.\n";
} finally {
    $repository = App::container()->get(AttemptRepository::class);
    $after = json_decode((string) file_get_contents($attemptsPath), true);
    foreach (is_array($after) ? $after : [] as $row) {
        $id = is_array($row) && is_scalar($row['id'] ?? null) ? (string) $row['id'] : '';
        if ($id !== '' && !in_array($id, $beforeIds, true)) {
            $repository->delete($id);
        }
    }
    WeaknessService::clear();
    WeaknessStorageService::save($beforeWeaknesses);
    file_put_contents($questionsPath, $questionsBefore, LOCK_EX);
}
