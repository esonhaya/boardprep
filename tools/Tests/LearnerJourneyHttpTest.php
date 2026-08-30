<?php

declare(strict_types=1);

require_once dirname(__DIR__) . "/Doctor/Project/BoardPrep/Simulation/HttpSimulator.php";
require_once dirname(__DIR__, 2) . "/app/Core/Autoloader.php";
\App\Core\Autoloader::register();

use App\Core\App;
use App\Repositories\AttemptRepository;
use App\Services\Learning\LearningAttemptNormalizer;
use App\Services\Learning\WeaknessService;
use App\Services\Learning\WeaknessStorageService;
use Tools\Doctor\Project\BoardPrep\Simulation\HttpSimulator;

$root = dirname(__DIR__, 2);
$attemptsPath = $root . '/storage/attempts.json';
$questionsPath = $root . '/storage/questions.json';
$questionsBefore = (string) file_get_contents($questionsPath);
$before = json_decode((string) file_get_contents($attemptsPath), true);
$before = is_array($before) ? $before : [];
$completedBefore = count(LearningAttemptNormalizer::all($before));
$beforeIds = array_values(array_filter(array_map(
    static fn(mixed $row): ?string => is_array($row) && is_scalar($row['id'] ?? null) ? (string) $row['id'] : null,
    $before
)));
$beforeWeaknesses = WeaknessStorageService::all();
$http = new HttpSimulator($root . '/public/index.php');
$cookies = ['PHPSESSID' => 'batch436-learner-journey'];

$complete = static function (array $query, bool $exerciseStaleForm = false) use ($http, $cookies): array {
    $start = $query;
    unset($start['action']);
    $page = $http->request('POST', '/quiz', [], ['action' => 'start'] + $start, [], $cookies);
    if (!$page['success'] || !preg_match('/Question 1 \/ (\d+)/', $page['output'], $totalMatch)) {
        throw new RuntimeException('targeted quiz did not start through normalized production path');
    }
    $total = (int) $totalMatch[1];
    $firstQuestionId = null;
    for ($index = 0; $index < $total; $index++) {
        if (!preg_match('/name="question_id"\s+value="([^"]+)"/', $page['output'], $id)) {
            throw new RuntimeException('quiz question id was not rendered');
        }
        $firstQuestionId ??= $id[1];
        $submit = $http->request('POST', '/quiz', [], [
            'action' => 'submit',
            'question_id' => $id[1],
            'answer' => 'A',
        ], [], $cookies);
        if (!$submit['success']) {
            throw new RuntimeException('quiz answer submission failed');
        }
        if ($index + 1 < $total) {
            $page = ($query['mode'] ?? 'practice') === 'exam'
                ? $submit
                : $http->request('POST', '/quiz', [], ['action' => 'next'], [], $cookies);
            if ($exerciseStaleForm && $index === 0) {
                $stale = $http->request('POST', '/quiz', [], [
                    'action' => 'submit',
                    'question_id' => $firstQuestionId,
                    'answer' => 'B',
                ], [], $cookies);
                if (!$stale['success'] || !str_contains($stale['output'], 'no longer active')
                    || !str_contains($stale['output'], 'Question 2 / ' . $total)) {
                    throw new RuntimeException('stale/back form was not rejected on the active question');
                }
            }
        }
    }
    $finish = $http->request('POST', '/quiz', [], ['action' => 'finish'], [], $cookies);
    $result = $http->request('GET', '/quiz', ['action' => 'result'], [], [], $cookies);
    $refresh = $http->request('GET', '/quiz', ['action' => 'result'], [], [], $cookies);
    if (!$finish['success'] || $finish['status'] !== 303
        || !$result['success'] || !$refresh['success'] || !str_contains($result['output'], 'Quiz Result')
        || !str_contains($result['output'], 'Answer Review')
        || !str_contains($result['output'], 'View Progress')) {
        throw new RuntimeException('quiz completion did not render');
    }

    return $result;
};

try {
    $landing = $http->request('GET', '/', [], [], [], $cookies);
    if (!$landing['success'] || !str_contains($landing['output'], 'Start Learning')
        || !str_contains($landing['output'], '/dashboard')) {
        throw new RuntimeException('fresh learner landing has no working learner entry points');
    }

    foreach (['/dashboard', '/history', '/profile', '/progress', '/study'] as $path) {
        $empty = $http->request('GET', $path, [], [], [], $cookies);
        if (!$empty['success']) {
            throw new RuntimeException("new learner page failed: {$path}");
        }
    }

    $firstResult = $complete([
        'action' => 'start', 'subject' => 'English', 'topic' => 'Subject-Verb Agreement',
        'mode' => 'practice', 'difficulty' => 'mixed', 'count' => 2,
    ], true);
    if (!preg_match('/(?:Practice this again|Practice again|Keep practicing)/', $firstResult['output'])
        || !str_contains($firstResult['output'], 'topic=Subject-Verb+Agreement')) {
        throw new RuntimeException('result action did not preserve score guidance and quiz context');
    }

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
        $expected = $completedBefore + 2;
        if (!$updated['success'] || !preg_match(
            '/(Completed Quizzes|Total Quizzes):\s*(?:<strong>)?' . $expected . '(?:<\/strong>)?(?!\d)/',
            $updated['output']
        )) {
            throw new RuntimeException("second completion is stale on {$path}");
        }
    }

    $simulationResult = $complete([
        'action' => 'start', 'exam' => 'LET', 'subject' => 'English',
        'mode' => 'exam', 'difficulty' => 'mixed', 'count' => 3,
    ]);
    if (substr_count($simulationResult['output'], 'question-review') !== 3) {
        throw new RuntimeException('exam simulation did not preserve its complete review');
    }

    $final = json_decode((string) file_get_contents($attemptsPath), true);
    $new = array_values(array_filter(is_array($final) ? $final : [], static fn(mixed $row): bool =>
        is_array($row) && is_scalar($row['id'] ?? null) && !in_array((string) $row['id'], $beforeIds, true)
    ));
    $types = array_count_values(array_map(
        static fn(array $row): string => (string) ($row['session_type'] ?? ''),
        $new
    ));
    if (count($new) !== 3 || ($types['quiz'] ?? 0) !== 2 || ($types['exam_simulation'] ?? 0) !== 1) {
        throw new RuntimeException('simulation corrupted or duplicated the ordinary quiz history');
    }
    foreach ($new as $attempt) {
        if (($attempt['subject'] ?? '') !== 'English'
            || !is_array($attempt['learning_context'] ?? null)) {
            throw new RuntimeException('final persisted attempt lost learner taxonomy context');
        }
    }
    foreach (['/dashboard', '/history', '/profile', '/progress', '/study'] as $path) {
        $updated = $http->request('GET', $path, [], [], [], $cookies);
        $expected = $completedBefore + 3;
        if (!$updated['success'] || !preg_match(
            '/(Completed Quizzes|Total Quizzes):\s*(?:<strong>)?' . $expected . '(?:<\/strong>)?(?!\d)/',
            $updated['output']
        )) {
            throw new RuntimeException("final learner state is stale on {$path}");
        }
    }

    echo "[PASS] fresh learner completes practice, review, recommendation, targeted retry, and simulation.\n";
    echo "[PASS] stale forms, result refresh, exact-once persistence, taxonomy, and final learning state verified.\n";
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
