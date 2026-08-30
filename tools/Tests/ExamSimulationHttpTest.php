<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Doctor/Project/BoardPrep/Simulation/HttpSimulator.php';
require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

use App\Core\App;
use App\Repositories\AttemptRepository;
use Tools\Doctor\Project\BoardPrep\Simulation\HttpSimulator;

$path = dirname(__DIR__, 2) . '/storage/attempts.json';
$questionsPath = dirname(__DIR__, 2) . '/storage/questions.json';
$weaknessPath = dirname(__DIR__, 2) . '/storage/weakness.json';
$questionsBefore = (string) file_get_contents($questionsPath);
$weaknessBefore = (string) file_get_contents($weaknessPath);
$before = json_decode((string) file_get_contents($path), true);
$before = is_array($before) ? $before : [];
$beforeIds = array_column($before, 'id');
$http = new HttpSimulator(dirname(__DIR__, 2) . '/public/index.php');
$cookies = ['PHPSESSID' => 'batch437-exam-simulation'];

try {
    $start = $http->request('POST', '/quiz', [], [
        'action' => 'start', 'exam' => 'LET', 'subject' => 'English',
        'mode' => 'exam', 'difficulty' => 'mixed', 'count' => 3,
    ], [], $cookies);
    if (!$start['success'] || !str_contains($start['output'], 'Question 1 / 3')
        || !preg_match('/name="question_id"\s+value="([^"]+)"/', $start['output'], $match)) {
        throw new RuntimeException('Three-question exam simulation did not start.');
    }

    $submit = $http->request('POST', '/quiz', [], [
        'action' => 'submit', 'question_id' => $match[1], 'answer' => 'A',
    ], [], $cookies);
    if (!$submit['success'] || !str_contains($submit['output'], 'Question 2 / 3')) {
        throw new RuntimeException('Exam navigation did not advance without practice feedback.');
    }

    $finish = $http->request('POST', '/quiz', [], ['action' => 'finish'], [], $cookies);
    $result = $http->request('GET', '/quiz', ['action' => 'result'], [], [], $cookies);
    $repeat = $http->request('GET', '/quiz', ['action' => 'result'], [], [], $cookies);
    $resultText = preg_replace('/\s+/', ' ', strip_tags($result['output'])) ?? '';
    if ($finish['status'] !== 303 || !$result['success'] || !$repeat['success']
        || !preg_match('/Score:\s*\d+\s*\/\s*3/', $resultText)
        || substr_count($result['output'], 'No answer') !== 2) {
        throw new RuntimeException('Partial simulation result did not retain the generated denominator.');
    }

    $after = json_decode((string) file_get_contents($path), true);
    $new = array_slice(is_array($after) ? $after : [], count($before));
    if (count($new) !== 1 || ($new[0]['session_type'] ?? '') !== 'exam_simulation'
        || (int) ($new[0]['question_count'] ?? 0) !== 3) {
        throw new RuntimeException(
            'Partial simulation was not persisted exactly once and isolated by type: '
            . json_encode($new)
        );
    }

    $retry = $http->request('POST', '/quiz', [], [
        'action' => 'start', 'subject' => 'English', 'mode' => 'practice', 'count' => 1,
    ], [], $cookies);
    $retryText = preg_replace('/\s+/', ' ', strip_tags($retry['output'])) ?? '';
    if (!$retry['success'] || !str_contains($retryText, 'Mode: Practice')) {
        throw new RuntimeException('A new ordinary quiz did not replace the completed simulation session.');
    }

    echo "[PASS] Partial/full-boundary exam HTTP journey and exact-once isolation verified.\n";
} finally {
    $repository = App::container()->get(AttemptRepository::class);
    $after = json_decode((string) file_get_contents($path), true);
    foreach (is_array($after) ? $after : [] as $attempt) {
        if (is_array($attempt) && isset($attempt['id'])
            && !in_array($attempt['id'], $beforeIds, true)) {
            $repository->delete((string) $attempt['id']);
        }
    }
    file_put_contents($questionsPath, $questionsBefore, LOCK_EX);
    file_put_contents($weaknessPath, $weaknessBefore, LOCK_EX);
}
