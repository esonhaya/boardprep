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

$attemptsPath = dirname(__DIR__, 2) . '/storage/attempts.json';
$before = json_decode((string) file_get_contents($attemptsPath), true);
$before = is_array($before) ? $before : [];
$beforeIds = array_values(array_filter(array_map(
    static fn(mixed $attempt): ?string => is_array($attempt) && is_scalar($attempt['id'] ?? null)
        ? (string) $attempt['id']
        : null,
    $before
)));
$beforeWeaknesses = WeaknessStorageService::all();

$simulator = new HttpSimulator(dirname(__DIR__, 2) . '/public/index.php');

$complete = static function (string $cookie, string $topic) use ($simulator): void {
    $cookies = ['PHPSESSID' => $cookie];
    $start = $simulator->request(
        'POST',
        '/quiz',
        [],
        [
            'action' => 'start',
            'subject' => 'English',
            'topic' => $topic,
            'mode' => 'exam',
            'difficulty' => 'mixed',
            'count' => 1,
        ],
        [],
        $cookies
    );

    if (!$start['success'] || !str_contains($start['output'], 'Question 1 / 1')
        || !preg_match('/name="question_id"\s+value="([^"]+)"/', $start['output'], $matches)) {
        throw new RuntimeException("could not start {$topic} quiz through HTTP");
    }

    $submit = $simulator->request(
        'POST',
        '/quiz',
        [],
        ['action' => 'submit', 'question_id' => $matches[1], 'answer' => 'A'],
        [],
        $cookies
    );
    if (!$submit['success'] || $submit['status'] !== 302) {
        throw new RuntimeException("could not submit {$topic} quiz through HTTP");
    }

    $result = $simulator->request('GET', '/quiz', ['action' => 'result'], [], [], $cookies);
    if (!$result['success'] || !str_contains($result['output'], 'Quiz Result')) {
        throw new RuntimeException("{$topic} result did not render through HTTP");
    }

    $simulator->request('GET', '/quiz', ['action' => 'result'], [], [], $cookies);
    $simulator->request(
        'POST',
        '/quiz',
        [],
        ['action' => 'submit', 'question_id' => $matches[1], 'answer' => 'B'],
        [],
        $cookies
    );
};

try {
    $learnerCookies = ['PHPSESSID' => 'batch428-learning'];
    $complete('batch428-learning', 'Subject-Verb Agreement');
    $complete('batch428-learning', 'Verb Tenses');

    $study = $simulator->request('GET', '/study', [], [], [], $learnerCookies);
    $progress = $simulator->request('GET', '/progress', [], [], [], $learnerCookies);
    $profile = $simulator->request('GET', '/profile', [], [], [], $learnerCookies);
    if (!$study['success'] || !$progress['success'] || !$profile['success']
        || !preg_match('/Subject(?:-|\s|\+)Verb(?:-|\s|\+)Agreement/i', $study['output'])
        || !str_contains($study['output'], 'Verb Tenses')
        || !preg_match('/Subject(?:-|\s|\+)Verb(?:-|\s|\+)Agreement/i', $progress['output'])
        || !str_contains($progress['output'], 'Verb Tenses')
        || !preg_match('/Subject(?:-|\s|\+)Verb(?:-|\s|\+)Agreement/i', $profile['output'])
        || !preg_match('/Practice Subject(?:-|\s|\+)Verb(?:-|\s|\+)Agreement/i', $profile['output'])) {
        throw new RuntimeException('multiple completed topics did not reach learner views');
    }

    $after = json_decode((string) file_get_contents($attemptsPath), true);
    $after = is_array($after) ? $after : [];
    $new = array_values(array_filter($after, static function (mixed $attempt) use ($beforeIds): bool {
        return is_array($attempt) && is_scalar($attempt['id'] ?? null)
            && !in_array((string) $attempt['id'], $beforeIds, true);
    }));
    if (count($new) !== 2
        || count(array_unique(array_map(static fn(array $attempt): string => (string) ($attempt['session_id'] ?? ''), $new))) !== 2
        || array_filter($new, static fn(array $attempt): bool => ($attempt['completed'] ?? false) !== true) !== []) {
        throw new RuntimeException('multiple completions overwrote or duplicated learning state');
    }

    echo "[PASS] two HTTP completions accumulate distinct learning state exactly once.\n";
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
    WeaknessService::clear();
    WeaknessStorageService::save($beforeWeaknesses);
}
