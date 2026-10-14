<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Doctor/Project/BoardPrep/Simulation/HttpSimulator.php';
require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

use App\Core\App;
use App\Repositories\AttemptRepository;
use Tools\Doctor\Project\BoardPrep\Simulation\HttpSimulator;

$root = dirname(__DIR__, 2);
$configuredStorage = trim((string) (getenv('APP_STORAGE_PATH') ?: ''));
$storagePath = $configuredStorage !== '' ? rtrim($configuredStorage, '/') : $root . '/storage';
$configuredSessionPath = trim((string) (getenv('APP_SESSION_PATH') ?: ''));
$sessionPath = $configuredSessionPath !== ''
    ? rtrim($configuredSessionPath, '/')
    : $storagePath . '/doctor/simulation-sessions';
if (!is_dir($sessionPath) && !mkdir($sessionPath, 0700, true) && !is_dir($sessionPath)) {
    throw new RuntimeException('Unable to create isolated exam-simulation session storage.');
}

$path = $storagePath . '/attempts.json';
$questionsPath = $storagePath . '/questions.json';
$weaknessPath = $storagePath . '/weakness.json';
$statisticsPath = $storagePath . '/question-statistics.json';
$questionsBefore = (string) file_get_contents($questionsPath);
$weaknessBefore = (string) file_get_contents($weaknessPath);
$statisticsBefore = is_file($statisticsPath) ? (string) file_get_contents($statisticsPath) : null;
$before = json_decode((string) file_get_contents($path), true);
$before = is_array($before) ? $before : [];
$beforeIds = array_column($before, 'id');
$http = new HttpSimulator($root . '/public/index.php');
$cookies = ['PHPSESSID' => 'batch676-exam-' . bin2hex(random_bytes(8))];

$readSession = static function (string $sessionId) use ($sessionPath): array {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    ini_set('session.use_cookies', '0');
    ini_set('session.use_strict_mode', '0');
    session_save_path($sessionPath);
    session_id($sessionId);
    if (!session_start()) {
        throw new RuntimeException('Unable to read isolated exam-simulation session.');
    }
    $data = $_SESSION;
    session_write_close();
    return is_array($data) ? $data : [];
};

try {
    $start = $http->request('POST', '/quiz', [], [
        'action' => 'start', 'exam' => 'LET', 'subject' => 'English',
        'mode' => 'exam', 'timer_mode' => 'timed', 'time_limit_minutes' => 5,
        'difficulty' => 'mixed', 'count' => 3,
    ], [], $cookies);
    if (!$start['success'] || !str_contains($start['output'], 'Question 1 / 3')
        || !str_contains($start['output'], 'Time remaining')
        || !str_contains($start['output'], 'separate from the official board examination')
        || !preg_match('/name="question_id"\s+value="([^"]+)"/', $start['output'], $match)) {
        throw new RuntimeException('Timed exam simulation did not start with its timer and official-exam disclosure.');
    }

    $startedSession = $readSession($cookies['PHPSESSID']);
    $startedQuiz = $startedSession['quiz_session'] ?? [];
    $deadline = is_array($startedQuiz) ? (int) ($startedQuiz['deadline_epoch'] ?? 0) : 0;
    $startedAt = is_array($startedQuiz) ? strtotime((string) ($startedQuiz['started_at'] ?? '')) : false;
    $deadlineOffset = $startedAt === false ? 0 : $deadline - $startedAt;
    if (($startedQuiz['timer_mode'] ?? '') !== 'timed'
        || (int) ($startedQuiz['time_limit_minutes'] ?? 0) !== 5
        || (int) ($startedQuiz['time_limit_seconds'] ?? 0) !== 300
        || $startedAt === false || $deadlineOffset < 299 || $deadlineOffset > 300) {
        throw new RuntimeException('Server deadline was not stored as the source of timer truth.');
    }

    $refresh = $http->request('GET', '/quiz', [], [], [], $cookies);
    $refreshedSession = $readSession($cookies['PHPSESSID']);
    if (!$refresh['success'] || !str_contains($refresh['output'], 'Question 1 / 3')
        || (int) ($refreshedSession['quiz_session']['deadline_epoch'] ?? 0) !== $deadline) {
        throw new RuntimeException('Refreshing an active simulation did not recover its question and original deadline.');
    }

    $firstAnswer = $http->request('POST', '/quiz', [], [
        'action' => 'submit', 'question_id' => $match[1], 'answer' => 'A',
    ], [], $cookies);
    if (!$firstAnswer['success'] || !str_contains($firstAnswer['output'], 'Question 2 / 3')
        || str_contains($firstAnswer['output'], 'Correct!') || str_contains($firstAnswer['output'], 'Incorrect')) {
        throw new RuntimeException('Exam simulation did not withhold immediate answer feedback.');
    }

    $skipSecond = $http->request('POST', '/quiz', [], ['action' => 'next'], [], $cookies);
    $skipLast = $http->request('POST', '/quiz', [], ['action' => 'next'], [], $cookies);
    if (!$skipSecond['success'] || !str_contains($skipSecond['output'], 'Question 3 / 3')
        || $skipLast['status'] !== 303 || $skipLast['location'] !== '/quiz?action=review') {
        throw new RuntimeException('Question navigation did not preserve skips or open review at the final question.');
    }

    $review = $http->request('GET', '/quiz', ['action' => 'review'], [], [], $cookies);
    if (!$review['success'] || !str_contains($review['output'], '1 of 3 answered')
        || !str_contains($review['output'], '2 will be marked unanswered')) {
        throw new RuntimeException('Pre-submit review did not identify unanswered questions.');
    }

    $jumpBack = $http->request('POST', '/quiz', [], ['action' => 'jump', 'index' => 0], [], $cookies);
    if (!$jumpBack['success'] || !preg_match('/name="answer"\s+value="A"\s+required\s+checked/', $jumpBack['output'])) {
        throw new RuntimeException('Returning to an answered question did not restore its saved selection.');
    }
    $changeAnswer = $http->request('POST', '/quiz', [], [
        'action' => 'submit', 'question_id' => $match[1], 'answer' => 'B',
    ], [], $cookies);
    if (!$changeAnswer['success'] || !str_contains($changeAnswer['output'], 'Question 2 / 3')) {
        throw new RuntimeException('Exam simulation did not allow a learner to change an answer before submission.');
    }

    $earlyFinish = $http->request('POST', '/quiz', [], ['action' => 'finish'], [], $cookies);
    if ($earlyFinish['status'] !== 303 || $earlyFinish['location'] !== '/quiz?action=review') {
        throw new RuntimeException('The final submit action bypassed a review after the answer set changed.');
    }
    $finalReview = $http->request('GET', '/quiz', ['action' => 'review'], [], [], $cookies);
    if (!str_contains($finalReview['output'], 'B.')) {
        throw new RuntimeException('Review did not show the updated answer.');
    }

    $concurrentFinishes = $http->requestsConcurrently([
        ['method' => 'POST', 'path' => '/quiz', 'post' => ['action' => 'finish'], 'cookies' => $cookies],
        ['method' => 'POST', 'path' => '/quiz', 'post' => ['action' => 'finish'], 'cookies' => $cookies],
    ]);
    $finish = $concurrentFinishes[0] ?? [];
    $secondFinish = $concurrentFinishes[1] ?? [];
    if (count($concurrentFinishes) !== 2
        || ($finish['status'] ?? 0) !== 303 || ($finish['location'] ?? null) !== '/quiz?action=result'
        || ($secondFinish['status'] ?? 0) !== 303 || ($secondFinish['location'] ?? null) !== '/quiz?action=result') {
        throw new RuntimeException('Simultaneous final-submit requests did not both recover to the single persisted result.');
    }
    $result = $http->request('GET', '/quiz', ['action' => 'result'], [], [], $cookies);
    $repeat = $http->request('GET', '/quiz', ['action' => 'result'], [], [], $cookies);
    $duplicateSubmit = $http->request('POST', '/quiz', [], [
        'action' => 'submit', 'question_id' => $match[1], 'answer' => 'A',
    ], [], $cookies);
    $resultText = preg_replace('/\s+/', ' ', strip_tags($result['output'])) ?? '';
    if ($finish['status'] !== 303 || !$result['success'] || !$repeat['success']
        || $duplicateSubmit['status'] !== 302
        || !str_contains($resultText, 'Exam Simulation Result')
        || !preg_match('/Score\s*:?[\s]*\d+\s*\/\s*3/', $resultText)
        || substr_count($result['output'], 'No answer') !== 2) {
        throw new RuntimeException('Partial simulation result or duplicate-submission protection failed.');
    }

    $after = json_decode((string) file_get_contents($path), true);
    $new = array_slice(is_array($after) ? $after : [], count($before));
    if (count($new) !== 1 || ($new[0]['session_type'] ?? '') !== 'exam_simulation'
        || ($new[0]['timer_mode'] ?? '') !== 'timed'
        || (int) ($new[0]['time_limit_minutes'] ?? 0) !== 5
        || ($new[0]['time_expired'] ?? true) !== false
        || (int) ($new[0]['question_count'] ?? 0) !== 3) {
        throw new RuntimeException('Simulation history did not preserve exact-once timer and question metadata: ' . json_encode($new));
    }
    $attemptId = is_scalar($new[0]['id'] ?? null) ? (string) $new[0]['id'] : '';
    $history = $http->request('GET', '/history', [], [], [], $cookies);
    $historicalResult = $attemptId !== ''
        ? $http->request('GET', '/history/result', ['id' => $attemptId], [], [], $cookies)
        : ['success' => false, 'output' => ''];
    $historyText = preg_replace('/\s+/', ' ', strip_tags($history['output'])) ?? '';
    $historicalText = preg_replace('/\s+/', ' ', strip_tags($historicalResult['output'])) ?? '';
    if (!$history['success'] || !$historicalResult['success']
        || !str_contains($historyText, 'Exam simulation')
        || !str_contains($historyText, 'Timed simulation · 5 min')
        || !str_contains($historicalText, 'Exam simulation')
        || !str_contains($historicalText, 'Timed simulation · 5 min')
        || !str_contains($historicalText, 'separate from the official board examination')) {
        throw new RuntimeException('History and historical result did not identify the timed attempt as a study simulation.');
    }

    $retry = $http->request('POST', '/quiz', [], [
        'action' => 'start', 'subject' => 'English', 'mode' => 'practice', 'count' => 1,
    ], [], $cookies);
    $retryText = preg_replace('/\s+/', ' ', strip_tags($retry['output'])) ?? '';
    if (!$retry['success'] || !str_contains($retryText, 'Mode: Practice')) {
        throw new RuntimeException('A new practice session did not replace the completed simulation.');
    }

$expiryCookies = ['PHPSESSID' => 'batch676-expired-exam-' . bin2hex(random_bytes(8))];
    $expiryStart = $http->request('POST', '/quiz', [], [
        'action' => 'start', 'exam' => 'LET', 'subject' => 'English',
        'mode' => 'exam', 'timer_mode' => 'timed', 'time_limit_minutes' => 1,
        'difficulty' => 'mixed', 'count' => 2,
    ], [], $expiryCookies);
    if (!$expiryStart['success'] || !str_contains($expiryStart['output'], 'Question 1 / 2')) {
        throw new RuntimeException('A second timed simulation could not start for expiration coverage.');
    }
    $expiredState = $readSession($expiryCookies['PHPSESSID']);
    $expiredQuiz = is_array($expiredState['quiz_session'] ?? null) ? $expiredState['quiz_session'] : [];
    $expiredQuiz['deadline_epoch'] = time() - 1;
    $expiredState['quiz_session'] = $expiredQuiz;
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    session_save_path($sessionPath);
    session_id($expiryCookies['PHPSESSID']);
    if (!session_start()) {
        throw new RuntimeException('Unable to set a deterministic expired deadline.');
    }
    $_SESSION['quiz_session'] = $expiredQuiz;
    session_write_close();

    $expiredRefresh = $http->request('GET', '/quiz', [], [], [], $expiryCookies);
    $expiredResult = $http->request('GET', '/quiz', ['action' => 'result'], [], [], $expiryCookies);
    $expiredText = preg_replace('/\s+/', ' ', strip_tags($expiredResult['output'])) ?? '';
    $afterExpiry = json_decode((string) file_get_contents($path), true);
    $allNewAttempts = array_slice(is_array($afterExpiry) ? $afterExpiry : [], count($before));
    $expiredAttempts = array_values(array_filter(
        $allNewAttempts,
        static fn(mixed $attempt): bool => is_array($attempt) && ($attempt['time_expired'] ?? false) === true
    ));
    if ($expiredRefresh['status'] !== 303 || $expiredRefresh['location'] !== '/quiz?action=result'
        || !$expiredResult['success'] || !str_contains($expiredText, 'Time expired; your saved answers were submitted.')
        || count($allNewAttempts) !== 2 || count($expiredAttempts) !== 1
        || (int) ($expiredAttempts[0]['unanswered'] ?? -1) !== 2) {
        throw new RuntimeException('Refreshing after the authoritative deadline did not submit once with unanswered items retained.');
    }

    echo "[PASS] Timed/untimed exam simulation setup, resume, navigation, editable answers, review, expiration, partial scoring, history metadata, retry, and concurrent exact-once submission verified.\n";
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
    if ($statisticsBefore !== null) {
        file_put_contents($statisticsPath, $statisticsBefore, LOCK_EX);
    }
}
