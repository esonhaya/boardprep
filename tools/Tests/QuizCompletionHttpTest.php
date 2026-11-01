<?php

declare(strict_types=1);

require_once dirname(__DIR__) . "/Doctor/Project/BoardPrep/Simulation/HttpSimulator.php";
require_once dirname(__DIR__, 2) . "/app/Core/Autoloader.php";
\App\Core\Autoloader::register();

use Tools\Doctor\Project\BoardPrep\Simulation\HttpSimulator;

$root = dirname(__DIR__, 2);
$fixturePath = sys_get_temp_dir() . '/boardprep-completion-' . bin2hex(random_bytes(6));
if (!mkdir($fixturePath, 0700, true)) {
    throw new RuntimeException('Unable to create isolated completion fixture.');
}
$mutableCollections = ['attempts', 'weakness', 'flashcard_state', 'question-statistics'];
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root . '/storage', FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);
foreach ($iterator as $entry) {
    $relative = substr($entry->getPathname(), strlen($root . '/storage') + 1);
    $target = $fixturePath . '/' . $relative;
    if ($entry->isDir()) {
        if (!is_dir($target) && !mkdir($target, 0700, true)) {
            throw new RuntimeException('Unable to create isolated storage directory.');
        }
        continue;
    }
    if (in_array(pathinfo($relative, PATHINFO_FILENAME), $mutableCollections, true)) {
        continue;
    }
    if (!copy($entry->getPathname(), $target)) {
        throw new RuntimeException('Unable to seed isolated completion storage.');
    }
}
foreach ($mutableCollections as $collection) {
    if (file_put_contents($fixturePath . '/' . $collection . '.json', "[]\n", LOCK_EX) === false) {
        throw new RuntimeException('Unable to initialize isolated learner state.');
    }
}
$originalEnvironment = [];
foreach (['APP_ENV', 'APP_DEBUG', 'APP_STORAGE_PATH', 'DB_DRIVER'] as $environmentKey) {
    $originalEnvironment[$environmentKey] = getenv($environmentKey);
}
putenv('APP_ENV=production');
putenv('APP_DEBUG=false');
putenv('APP_STORAGE_PATH=' . $fixturePath);
putenv('DB_DRIVER=json');
$attemptsPath = $fixturePath . '/attempts.json';
$before = json_decode((string) file_get_contents($attemptsPath), true);
$before = is_array($before) ? $before : [];
$beforeIds = array_values(array_filter(array_map(
    static fn(mixed $attempt): ?string => is_array($attempt) && is_scalar($attempt['id'] ?? null)
        ? (string) $attempt['id']
        : null,
    $before
)));

$simulator = new HttpSimulator($root . '/public/index.php');
$cookies = ['PHPSESSID' => 'batch665-completion-' . bin2hex(random_bytes(5))];

try {
    $settings = $simulator->request('GET', '/quiz', [], [], [], $cookies);
    if (!$settings['success'] || !preg_match('/name="csrf_token" value="([a-f0-9]{64})"/', $settings['output'], $csrfMatch)) {
        throw new RuntimeException('HTTP completion test could not obtain the quiz CSRF token');
    }

    $start = $simulator->request(
        'POST',
        '/quiz',
        [],
        [
            'action' => 'start',
            'exam' => 'LET',
            'subject' => 'English',
            'topic' => 'Subject-Verb Agreement',
            'mode' => 'exam',
            'difficulty' => 'mixed',
            'count' => 1,
            'csrf_token' => $csrfMatch[1],
        ],
        [],
        $cookies
    );

    if (!$start['success'] || !str_contains($start['output'], 'Question 1 / 1')
        || preg_match('/<strong>Board<\/strong>\s*Licensure Examination for Teachers/', $start['output']) !== 1
        || preg_match('/<strong>Subject<\/strong>\s*English/', $start['output']) !== 1) {
        preg_match('/<p class="quiz-context"[^>]*>.*?<\/p>/s', $start['output'], $contextMatch);
        throw new RuntimeException(
            'HTTP completion test could not start a context-labeled one-question quiz: '
            . (isset($contextMatch[0]) ? strip_tags($contextMatch[0]) : 'context block not rendered')
        );
    }

    if (!preg_match('/name="question_id"\s+value="([^"]+)"/', $start['output'], $matches)) {
        throw new RuntimeException('active question id was not rendered');
    }

    $submit = $simulator->request(
        'POST',
        '/quiz',
        [],
        ['action' => 'submit', 'question_id' => $matches[1], 'answer' => 'A', 'csrf_token' => $csrfMatch[1]],
        [],
        $cookies
    );
    if (!$submit['success'] || $submit['status'] !== 303 || $submit['location'] !== '/quiz?action=review') {
        throw new RuntimeException('final answer did not open answer review: HTTP '
            . $submit['status'] . ' ' . ($submit['location'] ?? '') . ' ' . substr($submit['stderr'], -240));
    }

    $review = $simulator->request('GET', '/quiz', ['action' => 'review'], [], [], $cookies);
    if (!$review['success'] || !str_contains($review['output'], 'Review your answers')) {
        throw new RuntimeException('exam result flow skipped pre-submit review');
    }
    $review = $simulator->request('GET', '/quiz', ['action' => 'review'], [], [], $cookies);
    if (!$review['success'] || !str_contains($review['output'], 'Review your answers')) {
        throw new RuntimeException('exam result flow skipped pre-submit review');
    }
    $finish = $simulator->request('POST', '/quiz', [], ['action' => 'finish', 'csrf_token' => $csrfMatch[1]], [], $cookies);
    $result = $simulator->request('GET', '/quiz', ['action' => 'result'], [], [], $cookies);
    if (!$finish['success'] || $finish['status'] !== 303
        || !$result['success'] || !str_contains($result['output'], 'Exam Simulation Result')
        || !str_contains($result['output'], 'Answer Review')
        || !str_contains($result['output'], 'Score percentage')
        || !str_contains($result['output'], 'Question answer accuracy')
        || !str_contains($result['output'], 'Question outcomes')) {
        throw new RuntimeException('completed result did not render through HTTP');
    }
    if (preg_match('/href="([^"]*\/question-report[^"]*)"/', $result['output'], $reportLinkMatch) !== 1) {
        throw new RuntimeException('completed result did not link question reporting to its owned attempt');
    }
    $reportUrl = html_entity_decode($reportLinkMatch[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    parse_str((string) parse_url($reportUrl, PHP_URL_QUERY), $reportQuery);
    if (!is_scalar($reportQuery['attempt_id'] ?? null)
        || !is_scalar($reportQuery['question_id'] ?? null)
        || (string) $reportQuery['question_id'] !== (string) $matches[1]) {
        throw new RuntimeException('result question report link omitted its attempt or did not match the reviewed question');
    }

    $study = $simulator->request('GET', '/study', [], [], [], $cookies);
    $progress = $simulator->request('GET', '/progress', [], [], [], $cookies);
    if (!$study['success'] || !preg_match('/Subject(?:-|\s|\+)Verb(?:-|\s|\+)Agreement/i', $study['output'])
        || !$progress['success'] || !preg_match('/Subject(?:-|\s|\+)Verb(?:-|\s|\+)Agreement/i', $progress['output'])) {
        throw new RuntimeException('completed learning state did not reach dashboard/history views');
    }

    $repeat = $simulator->request('GET', '/quiz', ['action' => 'result'], [], [], $cookies);
    $oldForm = $simulator->request(
        'POST',
        '/quiz',
        [],
        ['action' => 'submit', 'question_id' => $matches[1], 'answer' => 'B', 'csrf_token' => $csrfMatch[1]],
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

    if ((string) $reportQuery['attempt_id'] !== (string) ($new[0]['id'] ?? '')) {
        throw new RuntimeException('result question report link did not use the persisted attempt id');
    }
    $foreignCookies = ['PHPSESSID' => 'batch665-completion-foreign-' . bin2hex(random_bytes(5))];
    $foreignReport = $simulator->request('GET', '/question-report', $reportQuery, [], [], $foreignCookies);
    if ($foreignReport['status'] !== 404 || !str_contains((string) $foreignReport['output'], 'Question not found.')) {
        throw new RuntimeException('foreign attempt report request returned HTTP ' . $foreignReport['status'] . ': '
            . substr((string) $foreignReport['output'], 0, 360) . ' ' . substr((string) $foreignReport['stderr'], -360));
    }

    $questionRows = json_decode((string) file_get_contents($fixturePath . '/questions.json'), true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($questionRows)) {
        throw new RuntimeException('isolated question fixture was not a list');
    }
    $questionRows = array_values(array_filter($questionRows, static fn(mixed $question): bool => !is_array($question)
        || (string) ($question['id'] ?? '') !== (string) $reportQuery['question_id']));
    file_put_contents($fixturePath . '/questions.json', json_encode($questionRows, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n", LOCK_EX);
    $reportForm = $simulator->request('GET', '/question-report', $reportQuery, [], [], $cookies);
    if ($reportForm['status'] !== 200
        || !str_contains($reportForm['output'], 'Report a question concern')
        || preg_match('/name="csrf_token" value="([a-f0-9]{64})"/', $reportForm['output'], $reportCsrf) !== 1
        || !str_contains($reportForm['output'], 'completed quiz result')) {
        throw new RuntimeException('owned historical report form returned HTTP ' . $reportForm['status'] . ': '
            . substr(strip_tags((string) $reportForm['output']), 0, 320) . ' ' . substr((string) $reportForm['stderr'], -320));
    }
    $reportCsrfToken = html_entity_decode($reportCsrf[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $reportSubmit = $simulator->request('POST', '/question-report', [], [
        'csrf_token' => $reportCsrfToken,
        'attempt_id' => (string) $reportQuery['attempt_id'],
        'question_id' => (string) $reportQuery['question_id'],
        'type' => 'SOURCE_CONCERN',
        'description' => 'Synthetic archived question report fixture.',
    ], [], $cookies);
    if (!$reportSubmit['success'] || $reportSubmit['status'] !== 303
        || $reportSubmit['location'] !== '/question-reports') {
        throw new RuntimeException('owned attempt question report did not persist through POST/Redirect/GET');
    }
    $reportRecords = json_decode((string) file_get_contents($fixturePath . '/learner-question-reports.json'), true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($reportRecords) || count($reportRecords) !== 1
        || ($reportRecords[0]['attempt_id'] ?? null) !== $new[0]['id']
        || ($reportRecords[0]['question_id'] ?? null) !== $reportQuery['question_id']) {
        throw new RuntimeException('question concern was not bound to its owner-verified attempt snapshot');
    }

    echo "[PASS] HTTP completion persists one attempt and binds archived question reporting to its owner-verified result.\n";
} finally {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($fixturePath, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $entry) {
        if ($entry->isDir()) {
            @rmdir($entry->getPathname());
        } else {
            @unlink($entry->getPathname());
        }
    }
    @rmdir($fixturePath);
    $sessionDirectory = trim((string) (getenv('APP_SESSION_PATH') ?: ''));
    if ($sessionDirectory === '') {
        $sessionDirectory = $root . '/storage/doctor/simulation-sessions';
    }
    @unlink(rtrim($sessionDirectory, DIRECTORY_SEPARATOR) . '/sess_' . $cookies['PHPSESSID']);
    if (isset($foreignCookies['PHPSESSID'])) {
        @unlink(rtrim($sessionDirectory, DIRECTORY_SEPARATOR) . '/sess_' . $foreignCookies['PHPSESSID']);
    }
    foreach ($originalEnvironment as $environmentKey => $value) {
        if ($value === false) {
            putenv($environmentKey);
        } else {
            putenv($environmentKey . '=' . $value);
        }
    }
}
