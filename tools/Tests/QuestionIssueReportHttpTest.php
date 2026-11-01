<?php

declare(strict_types=1);

putenv('APP_ENV=testing');
putenv('APP_DEBUG=false');
putenv('BOARDPREP_ACCOUNTS_ENABLED=false');

$root = sys_get_temp_dir() . '/boardprep-b732-report-http-' . bin2hex(random_bytes(6));
if (!mkdir($root, 0700, true)) {
    throw new RuntimeException('Unable to create the isolated question-report HTTP fixture.');
}
$sqlitePath = $root . '/http.sqlite';
putenv('DB_DRIVER=sqlite');
putenv('DB_SQLITE_PATH=' . $sqlitePath);

require_once dirname(__DIR__) . '/Doctor/Project/BoardPrep/Simulation/HttpSimulator.php';
require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

use Tools\Doctor\Project\BoardPrep\Simulation\HttpSimulator;
use Tools\Doctor\Project\BoardPrep\Simulation\SimulationStorageFixture;

function issueReportHttpAssert(bool $ok, string $message): void
{
    if (!$ok) {
        throw new RuntimeException($message);
    }
}

try {
    $fixtureStorage = SimulationStorageFixture::storagePath();
    $questions = json_decode((string) file_get_contents($fixtureStorage . '/questions.json'), true, 512, JSON_THROW_ON_ERROR);
    $question = null;
    foreach (is_array($questions) ? $questions : [] as $candidate) {
        if (is_array($candidate) && is_scalar($candidate['id'] ?? null)
            && is_array($candidate['taxonomy'] ?? null)
            && trim((string) ($candidate['taxonomy']['board_id'] ?? '')) !== ''
            && trim((string) ($candidate['taxonomy']['subject_id'] ?? '')) !== '') {
            $question = $candidate;
            break;
        }
    }
    issueReportHttpAssert(is_array($question), 'Fixture has no canonical question with a board and subject.');
    $questionId = (string) $question['id'];
    $http = new HttpSimulator(dirname(__DIR__, 2) . '/public/index.php');
    $learnerA = ['PHPSESSID' => 'b732-report-a-' . bin2hex(random_bytes(8))];
    $learnerB = ['PHPSESSID' => 'b732-report-b-' . bin2hex(random_bytes(8))];

    $settings = $http->request('GET', '/quiz', [], [], [], $learnerA);
    issueReportHttpAssert(preg_match('/name="csrf_token" value="([a-f0-9]{64})"/', $settings['output'], $quizCsrf) === 1,
        'Quiz setup omitted its session token.');
    $started = $http->request('POST', '/quiz', [], [
        'action' => 'start', 'exam' => 'LET', 'subject' => 'English', 'mode' => 'exam',
        'difficulty' => 'mixed', 'count' => 1, 'csrf_token' => $quizCsrf[1],
    ], [], $learnerA);
    issueReportHttpAssert(str_contains($started['output'], 'Question 1 / 1')
        && preg_match('/name="question_id"\s+value="([^"]+)"/', $started['output'], $activeQuestion) === 1,
        'Could not start a one-question synthetic report fixture.');
    $questionId = (string) $activeQuestion[1];
    $answer = $http->request('POST', '/quiz', [], [
        'action' => 'submit', 'question_id' => $questionId, 'answer' => 'A', 'csrf_token' => $quizCsrf[1],
    ], [], $learnerA);
    issueReportHttpAssert($answer['status'] === 303 && $answer['location'] === '/quiz?action=review',
        'Synthetic quiz answer did not enter exam review.');
    $review = $http->request('GET', '/quiz', ['action' => 'review'], [], [], $learnerA);
    issueReportHttpAssert(str_contains($review['output'], 'Review your answers'), 'Synthetic exam review was unavailable.');
    $finished = $http->request('POST', '/quiz', [], ['action' => 'finish', 'csrf_token' => $quizCsrf[1]], [], $learnerA);
    issueReportHttpAssert($finished['status'] === 303, 'Synthetic exam did not finish.');
    $result = $http->request('GET', '/quiz', ['action' => 'result'], [], [], $learnerA);
    issueReportHttpAssert(preg_match('/href="([^"]*\/question-report[^"]*)"/', $result['output'], $reportLink) === 1,
        'Completed result omitted its question-report action.');
    $reportUrl = html_entity_decode($reportLink[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    parse_str((string) parse_url($reportUrl, PHP_URL_QUERY), $reportQuery);
    $questionId = (string) ($reportQuery['question_id'] ?? '');
    $attemptId = (string) ($reportQuery['attempt_id'] ?? '');
    issueReportHttpAssert($questionId !== '' && $attemptId !== '', 'Result report action omitted attempt ownership context.');

    $unboundForm = $http->request('GET', '/question-report', ['question_id' => $questionId], [], [], $learnerA);
    issueReportHttpAssert($unboundForm['status'] === 404, 'Question report form accepted an unbound canonical question.');
    $foreignAttemptForm = $http->request('GET', '/question-report', $reportQuery, [], [], $learnerB);
    issueReportHttpAssert($foreignAttemptForm['status'] === 404, 'A learner could open a report form for another learner’s attempt.');

    $form = $http->request('GET', '/question-report', $reportQuery, [], [], $learnerA);
    issueReportHttpAssert($form['status'] === 200
        && str_contains($form['output'], 'Report a question concern')
        && str_contains($form['output'], 'completed quiz result')
        && str_contains($form['output'], 'for="question-report-type"')
        && str_contains($form['output'], 'id="question-report-type"')
        && str_contains($form['output'], 'for="question-report-description"')
        && str_contains($form['output'], 'maxlength="500"')
        && !str_contains($form['output'], 'Correct answer')
        && !str_contains($form['output'], 'name="answer"'),
        'Question report form did not render without disclosing an answer key.');
    issueReportHttpAssert(preg_match('/name="csrf_token" value="([^"]+)"/', $form['output'], $csrf) === 1,
        'Question report form omitted CSRF protection.');
    $token = html_entity_decode($csrf[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');

    $missingCsrf = $http->request('POST', '/question-report', [], [
        'attempt_id' => $attemptId, 'question_id' => $questionId, 'type' => 'SOURCE_CONCERN', 'description' => 'No token',
    ], [], $learnerA);
    issueReportHttpAssert($missingCsrf['status'] === 303, 'Missing CSRF did not use the safe form redirect.');
    $badType = $http->request('POST', '/question-report', [], [
        'csrf_token' => $token, 'attempt_id' => $attemptId, 'question_id' => $questionId, 'type' => 'APPROVE_ANSWER',
    ], [], $learnerA);
    issueReportHttpAssert($badType['status'] === 303, 'An unrecognized concern type was not rejected.');
    $arrayType = $http->request('POST', '/question-report', [], [
        'csrf_token' => $token, 'attempt_id' => $attemptId, 'question_id' => $questionId, 'type' => ['OTHER'], 'description' => '',
    ], [], $learnerA);
    issueReportHttpAssert($arrayType['status'] === 303, 'An array-valued concern type was not rejected.');
    $unknownQuestion = $http->request('POST', '/question-report', [], [
        'csrf_token' => $token, 'attempt_id' => $attemptId, 'question_id' => 'unknown-question-id', 'type' => 'SOURCE_CONCERN', 'description' => 'Not found',
    ], [], $learnerA);
    issueReportHttpAssert($unknownQuestion['status'] === 303, 'A stale question identifier was not rejected safely.');
    $oversizedDescription = $http->request('POST', '/question-report', [], [
        'csrf_token' => $token, 'attempt_id' => $attemptId, 'question_id' => $questionId, 'type' => 'SOURCE_CONCERN', 'description' => str_repeat('x', 501),
    ], [], $learnerA);
    issueReportHttpAssert($oversizedDescription['status'] === 303, 'An oversized report note was not rejected safely.');
    $stillEmpty = $http->request('GET', '/question-reports', [], [], [], $learnerA);
    issueReportHttpAssert($stillEmpty['status'] === 200 && str_contains($stillEmpty['output'], 'No reports yet'),
        'Invalid issue-report requests persisted a report.');

    $post = [
        'csrf_token' => $token,
        'attempt_id' => $attemptId,
        'question_id' => $questionId,
        'type' => 'SOURCE_CONCERN',
        'description' => '<script>alert("x")</script> Please check the reference.',
    ];
    $submitted = $http->request('POST', '/question-report', [], $post, [], $learnerA);
    issueReportHttpAssert($submitted['status'] === 303 && $submitted['location'] === '/question-reports',
        'Valid learner report did not use POST/Redirect/GET.');
    $retry = $http->request('POST', '/question-report', [], $post, [], $learnerA);
    issueReportHttpAssert($retry['status'] === 303, 'A duplicate submission retry did not return safely.');
    $mine = $http->request('GET', '/question-reports', [], [], [], $learnerA);
    issueReportHttpAssert($mine['status'] === 200
        && str_contains($mine['output'], 'SUBMITTED')
        && str_contains($mine['output'], '&lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt;')
        && !str_contains($mine['output'], '<script>alert("x")</script>')
        && !str_contains($mine['output'], $attemptId),
        'Learner status view failed to escape the report text or display its real status.');
    $otherLearner = $http->request('GET', '/question-reports', [], [], [], $learnerB);
    issueReportHttpAssert($otherLearner['status'] === 200
        && str_contains($otherLearner['output'], 'No reports yet')
        && !str_contains($otherLearner['output'], 'learner-question-report-record'),
        'A second learner could see another learner report.');
    $reviewerWithoutLogin = $http->request('GET', '/developer/question-reports');
    issueReportHttpAssert($reviewerWithoutLogin['status'] === 401,
        'Reviewer queue did not require an authenticated account.');

    $reportPath = $fixtureStorage . '/learner-question-reports.json';
    $records = json_decode((string) file_get_contents($reportPath), true, 512, JSON_THROW_ON_ERROR);
    issueReportHttpAssert(is_array($records) && count($records) === 1, 'The JSON simulation did not store exactly one idempotent report.');
    issueReportHttpAssert(($records[0]['status'] ?? null) === 'SUBMITTED'
        && ($records[0]['user_id'] ?? null) !== ''
        && ($records[0]['question_id'] ?? null) === $questionId
        && ($records[0]['attempt_id'] ?? null) === $attemptId
        && count((array) ($records[0]['status_history'] ?? [])) === 1,
        'Report ownership, attempt reference, or initial status history was invalid.');
    issueReportHttpAssert(!array_key_exists('answer', $records[0]) && !array_key_exists('explanation', $records[0]),
        'Learner report persistence copied protected answer or explanation content.');

    echo "[PASS] Owner-verified result reporting, CSRF/input validation, duplicate retry, XSS-safe private status, account isolation, reviewer gate, JSON storage, and canonical-data separation.\n";
} finally {
    foreach (scandir($root) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') continue;
        $path = $root . DIRECTORY_SEPARATOR . $entry;
        if (is_file($path) || is_link($path)) @unlink($path);
    }
    @rmdir($root);
}
