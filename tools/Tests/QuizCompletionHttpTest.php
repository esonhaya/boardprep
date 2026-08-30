<?php

declare(strict_types=1);

require_once dirname(__DIR__) . "/Doctor/Project/BoardPrep/Simulation/HttpSimulator.php";
require_once dirname(__DIR__, 2) . "/app/Core/Autoloader.php";
\App\Core\Autoloader::register();

use App\Core\App;
use App\Repositories\AttemptRepository;
use Tools\Doctor\Project\BoardPrep\Simulation\HttpSimulator;

$attemptsPath = dirname(__DIR__, 2) . '/storage/attempts.json';
$before = json_decode((string) file_get_contents($attemptsPath), true);
$before = is_array($before) ? $before : [];
$beforeIds = array_values(array_filter(array_map(
    static fn(mixed $attempt): ?string => is_array($attempt) && is_scalar($attempt['id'] ?? null)
        ? (string) $attempt['id']
        : null,
    $before
)));

$simulator = new HttpSimulator(dirname(__DIR__, 2) . '/public/index.php');
$cookies = ['PHPSESSID' => 'batch426-completion'];

try {
    $start = $simulator->request(
        'GET',
        '/quiz',
        [
            'action' => 'start',
            'subject' => 'English',
            'topic' => 'Subject-Verb Agreement',
            'mode' => 'exam',
            'difficulty' => 'mixed',
            'count' => 1,
        ],
        [],
        [],
        $cookies
    );

    if (!$start['success'] || !str_contains($start['output'], 'Question 1 / 1')) {
        throw new RuntimeException('HTTP completion test could not start one-question quiz');
    }

    if (!preg_match('/name="question_id"\s+value="([^"]+)"/', $start['output'], $matches)) {
        throw new RuntimeException('active question id was not rendered');
    }

    $submit = $simulator->request(
        'POST',
        '/quiz',
        ['action' => 'submit'],
        ['question_id' => $matches[1], 'answer' => 'A'],
        [],
        $cookies
    );
    if (!$submit['success'] || $submit['status'] !== 302) {
        throw new RuntimeException('final answer did not redirect to completion');
    }

    $result = $simulator->request('GET', '/quiz', ['action' => 'finish'], [], [], $cookies);
    if (!$result['success'] || !str_contains($result['output'], 'Quiz Result')
        || !str_contains($result['output'], 'Answer Review')) {
        throw new RuntimeException('completed result did not render through HTTP');
    }

    $repeat = $simulator->request('GET', '/quiz', ['action' => 'finish'], [], [], $cookies);
    $oldForm = $simulator->request(
        'POST',
        '/quiz',
        ['action' => 'submit'],
        ['question_id' => $matches[1], 'answer' => 'B'],
        [],
        $cookies
    );
    if (!$repeat['success'] || !$oldForm['success']) {
        throw new RuntimeException('completed retry requests were not safely handled');
    }

    $after = json_decode((string) file_get_contents($attemptsPath), true);
    $after = is_array($after) ? $after : [];
    $new = array_values(array_filter($after, static function (mixed $attempt) use ($beforeIds): bool {
        return is_array($attempt) && is_scalar($attempt['id'] ?? null)
            && !in_array((string) $attempt['id'], $beforeIds, true);
    }));
    if (count($new) !== 1 || ($new[0]['completed'] ?? false) !== true
        || (int) ($new[0]['question_count'] ?? 0) !== 1) {
        throw new RuntimeException('completion did not persist exactly one canonical attempt');
    }

    echo "[PASS] HTTP completion renders result and persists one attempt across retries.\n";
} finally {
    $repository = App::container()->get(AttemptRepository::class);
    $after = json_decode((string) file_get_contents($attemptsPath), true);
    foreach (is_array($after) ? $after : [] as $attempt) {
        $id = is_array($attempt) && is_scalar($attempt['id'] ?? null)
            ? (string) $attempt['id']
            : '';
        if ($id !== '' && !in_array($id, $beforeIds, true)) {
            $repository->delete($id);
        }
    }
}
