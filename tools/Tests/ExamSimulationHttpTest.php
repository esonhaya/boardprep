<?php

declare(strict_types=1);

putenv('BOARDPREP_SIMULATION_SEED=6801');
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
    $settings = $http->request('GET', '/quiz', [], [], [], $cookies);
    if (!$settings['success'] || $settings['status'] !== 200
        || !preg_match('/name="csrf_token" value="([^"]+)"/', $settings['output'], $csrfMatch)) {
        throw new RuntimeException('Quiz settings did not provide a CSRF token for exam simulation.');
    }
    $csrfToken = $csrfMatch[1];

    $blueprintPreview = $http->request('GET', '/quiz', [
        'exam' => 'CE', 'subject' => '*', 'mode' => 'exam', 'count' => 20,
    ], [], [], $cookies);
    preg_match('/<section[^>]*data-exam-blueprint-preview[^>]*data-board="ce".*?<\/section>/s', $blueprintPreview['output'], $cePreviewMatch);
    $cePreview = (string) ($cePreviewMatch[0] ?? '');
    if (!$blueprintPreview['success'] || $blueprintPreview['status'] !== 200
        || !str_contains($cePreview, 'Subject distribution')
        || !str_contains($cePreview, 'Principles of Structural Analysis and Design')
        || !preg_match('/data-blueprint-count[^>]*>7<\/span> of <span data-blueprint-total[^>]*>20<\/span> requested questions/', $cePreview)
        || !str_contains($cePreview, 'Registered source weighting')) {
        throw new RuntimeException('Exam setup did not preview the selected board\'s registered weighting and exact question allocation: '
            . json_encode(['status' => $blueprintPreview['status'] ?? null, 'preview' => substr(strip_tags($cePreview), 0, 700)]));
    }

    $focusedBlueprintPreview = $http->request('GET', '/quiz', [
        'exam' => 'CE', 'subject' => 'civil-engineering-structural-engineering', 'mode' => 'exam', 'count' => 20,
    ], [], [], $cookies);
    preg_match('/<section[^>]*data-exam-blueprint-preview[^>]*data-board="ce".*?<\/section>/s', $focusedBlueprintPreview['output'], $focusedPreviewMatch);
    $focusedPreview = (string) ($focusedPreviewMatch[0] ?? '');
    if (!$focusedBlueprintPreview['success'] || $focusedBlueprintPreview['status'] !== 200
        || !str_contains($focusedPreview, 'Focused subject session')
        || !str_contains($focusedPreview, 'does not represent the full-board subject distribution')
        || str_contains($focusedPreview, 'data-blueprint-count')) {
        throw new RuntimeException('Focused subject setup falsely displayed a full-board distribution.');
    }

    $start = $http->request('POST', '/quiz', [], [
        'action' => 'start', 'exam' => 'LET', 'subject' => 'English',
        'mode' => 'exam', 'timer_mode' => 'timed', 'time_limit_minutes' => 5,
        'difficulty' => 'mixed', 'count' => 3, 'csrf_token' => $csrfToken,
    ], [], $cookies);
    if (!$start['success'] || !str_contains($start['output'], 'Question 1 / 3')
        || !str_contains($start['output'], '0 of 3 answers are currently recorded in this exam session')
        || !str_contains($start['output'], 'An internet connection is required to save or change answers')
        || !str_contains($start['output'], 'Time remaining')
        || !str_contains($start['output'], 'separate from the official board examination')
        || !preg_match('/name="question_id"\s+value="([^"]+)"/', $start['output'], $match)) {
        throw new RuntimeException('Timed exam simulation did not start with its timer and official-exam disclosure.');
    }

    $activeDashboard = $http->request('GET', '/dashboard', [], [], [], $cookies);
    if (!$activeDashboard['success'] || !str_contains($activeDashboard['output'], 'data-active-exam-state="ACTIVE_SESSION"')
        || !str_contains($activeDashboard['output'], 'Resume exam simulation')
        || !str_contains($activeDashboard['output'], '0 answers recorded by the server')) {
        throw new RuntimeException('The learner dashboard did not offer a truthful resume action for this active exam session.');
    }

    $startedSession = $readSession($cookies['PHPSESSID']);
    $startedQuiz = $startedSession['quiz_session'] ?? [];
    $deadline = is_array($startedQuiz) ? (int) ($startedQuiz['deadline_epoch'] ?? 0) : 0;
    $startedAt = is_array($startedQuiz) ? strtotime((string) ($startedQuiz['started_at'] ?? '')) : false;
    $deadlineOffset = $startedAt === false ? 0 : $deadline - $startedAt;
    if (($startedQuiz['timer_mode'] ?? '') !== 'timed'
        || ($startedSession['exam_review_flag_session_id'] ?? '') !== ($startedQuiz['id'] ?? null)
        || (array) ($startedSession['exam_review_flags'] ?? []) !== []
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

    $unboundNavigation = $http->request('POST', '/quiz', [], [
        'action' => 'next', 'csrf_token' => $csrfToken,
    ], [], $cookies);
    $unboundNavigationState = $readSession($cookies['PHPSESSID']);
    if (!$unboundNavigation['success'] || !str_contains($unboundNavigation['output'], 'Question 1 / 3')
        || (int) ($unboundNavigationState['currentQuestion'] ?? -1) !== 0) {
        throw new RuntimeException('An exam navigation request without its rendered question identity moved the session.');
    }

    $tamperedQuestion = $http->request('POST', '/quiz', [], [
        'action' => 'submit', 'question_id' => 'not-in-this-session', 'answer' => 'A', 'csrf_token' => $csrfToken,
    ], [], $cookies);
    $afterTamperedQuestion = $readSession($cookies['PHPSESSID']);
    if (!$tamperedQuestion['success'] || (int) ($afterTamperedQuestion['currentQuestion'] ?? -1) !== 0
        || (array) ($afterTamperedQuestion['answers'] ?? []) !== []) {
        throw new RuntimeException('A manipulated question identifier changed the active session or persisted an answer.');
    }

    $markFirst = $http->request('POST', '/quiz', [], [
        'action' => 'review-flag', 'index' => 0, 'question_id' => $match[1],
        'marked' => '1', 'version' => '0', 'return_to' => 'current', 'csrf_token' => $csrfToken,
    ], [], $cookies);
    $markedFirstState = $readSession($cookies['PHPSESSID']);
    $markedFirstPage = $http->request('GET', '/quiz', [], [], [], $cookies);
    if ($markFirst['status'] !== 303 || $markFirst['location'] !== '/quiz'
        || ($markedFirstState['exam_review_flags'][0] ?? false) !== true
        || (int) ($markedFirstState['exam_review_flag_versions'][0] ?? -1) !== 1
        || ($markedFirstState['answers'] ?? []) !== []
        || (int) ($markedFirstState['currentQuestion'] ?? -1) !== 0
        || !$markedFirstPage['success'] || !str_contains($markedFirstPage['output'], 'aria-pressed="true"')
        || !str_contains($markedFirstPage['output'], 'Remove review mark')) {
        throw new RuntimeException('Marking an unanswered question did not persist an accessible review state independently of answers and navigation.');
    }

    // The release runner executes this script in APP_ENV=testing. Explicitly
    // exercise the production CSRF boundary for these two HTTP requests so an
    // invalid token cannot silently unmark an otherwise valid learner flag.
    $previousAppEnvironment = getenv('APP_ENV');
    putenv('APP_ENV=production');
    try {
        $invalidCsrfFlag = $http->request('POST', '/quiz', [], [
            'action' => 'review-flag', 'index' => 0, 'question_id' => $match[1],
            'marked' => '0', 'version' => '1', 'return_to' => 'current', 'csrf_token' => 'invalid-token',
        ], [], $cookies);
        $afterInvalidCsrfFlag = $readSession($cookies['PHPSESSID']);
        $foreignCookies = ['PHPSESSID' => 'batch726-foreign-flag-' . bin2hex(random_bytes(8))];
        $foreignFlagRequest = $http->request('POST', '/quiz', [], [
            'action' => 'review-flag', 'index' => 0, 'question_id' => $match[1],
            'marked' => '1', 'version' => '0', 'return_to' => 'current', 'csrf_token' => $csrfToken,
        ], [], $foreignCookies);
        $foreignFlagState = $readSession($foreignCookies['PHPSESSID']);
    } finally {
        if ($previousAppEnvironment === false) {
            putenv('APP_ENV');
        } else {
            putenv('APP_ENV=' . $previousAppEnvironment);
        }
    }
    $foreignDashboard = $http->request('GET', '/dashboard', [], [], [], $foreignCookies);
    if (!in_array($invalidCsrfFlag['status'], [302, 303], true)
        || ($afterInvalidCsrfFlag['exam_review_flags'][0] ?? false) !== true
        || !in_array($foreignFlagRequest['status'], [302, 303], true)
        || array_key_exists('questions', $foreignFlagState)
        || array_key_exists('quiz_session', $foreignFlagState)
        || array_key_exists('exam_review_flags', $foreignFlagState)
        || str_contains($foreignDashboard['output'], 'data-active-exam-state')) {
        throw new RuntimeException('CSRF or cross-session access changed an exam review mark outside its owning browser session.');
    }

    $duplicateMark = $http->request('POST', '/quiz', [], [
        'action' => 'review-flag', 'index' => 0, 'question_id' => $match[1],
        'marked' => '1', 'version' => '0', 'return_to' => 'current', 'csrf_token' => $csrfToken,
    ], [], $cookies);
    $afterDuplicateMark = $readSession($cookies['PHPSESSID']);
    $unmarkFirst = $http->request('POST', '/quiz', [], [
        'action' => 'review-flag', 'index' => 0, 'question_id' => $match[1],
        'marked' => '0', 'version' => '1', 'return_to' => 'current', 'csrf_token' => $csrfToken,
    ], [], $cookies);
    $afterUnmark = $readSession($cookies['PHPSESSID']);
    $staleMark = $http->request('POST', '/quiz', [], [
        'action' => 'review-flag', 'index' => 0, 'question_id' => $match[1],
        'marked' => '1', 'version' => '0', 'return_to' => 'current', 'csrf_token' => $csrfToken,
    ], [], $cookies);
    $afterStaleMark = $readSession($cookies['PHPSESSID']);
    $freshMark = $http->request('POST', '/quiz', [], [
        'action' => 'review-flag', 'index' => 0, 'question_id' => $match[1],
        'marked' => '1', 'version' => '2', 'return_to' => 'current', 'csrf_token' => $csrfToken,
    ], [], $cookies);
    $afterFreshMark = $readSession($cookies['PHPSESSID']);
    if ($duplicateMark['status'] !== 303
        || (int) ($afterDuplicateMark['exam_review_flag_versions'][0] ?? -1) !== 1
        || $unmarkFirst['status'] !== 303 || ($afterUnmark['exam_review_flags'][0] ?? false) !== false
        || (int) ($afterUnmark['exam_review_flag_versions'][0] ?? -1) !== 2
        || $staleMark['status'] !== 303 || ($afterStaleMark['exam_review_flags'][0] ?? false) !== false
        || (int) ($afterStaleMark['exam_review_flag_versions'][0] ?? -1) !== 2
        || $freshMark['status'] !== 303 || ($afterFreshMark['exam_review_flags'][0] ?? false) !== true
        || (int) ($afterFreshMark['exam_review_flag_versions'][0] ?? -1) !== 3) {
        throw new RuntimeException('Review mark retries, unmarking, or stale conflicting requests changed the wrong persisted flag state.');
    }

    $invalidReviewMark = $http->request('POST', '/quiz', [], [
        'action' => 'review-flag', 'index' => 0, 'question_id' => 'not-in-this-session',
        'marked' => '0', 'version' => '3', 'return_to' => 'current', 'csrf_token' => $csrfToken,
    ], [], $cookies);
    $afterInvalidReviewMark = $readSession($cookies['PHPSESSID']);
    if ($invalidReviewMark['status'] !== 303
        || ($afterInvalidReviewMark['exam_review_flags'][0] ?? false) !== true
        || (int) ($afterInvalidReviewMark['exam_review_flag_versions'][0] ?? -1) !== 3) {
        throw new RuntimeException('An invalid review question identifier changed the active exam review state.');
    }

    $firstAnswer = $http->request('POST', '/quiz', [], [
        'action' => 'submit', 'question_id' => $match[1], 'answer' => 'A', 'csrf_token' => $csrfToken,
    ], [], $cookies);
    if (!$firstAnswer['success'] || !str_contains($firstAnswer['output'], 'Question 2 / 3')
        || !str_contains($firstAnswer['output'], '1 of 3 answers are currently recorded in this exam session')
        || preg_match('/<section[^>]*class="[^"]*\bquiz-feedback\b/i', $firstAnswer['output']) === 1) {
        throw new RuntimeException('Exam simulation did not advance without rendering its immediate answer-feedback component.');
    }

    $questionTwoId = (string) (($readSession($cookies['PHPSESSID'])['questions'][1]['id'] ?? ''));
    $markUnansweredSecond = $http->request('POST', '/quiz', [], [
        'action' => 'review-flag', 'index' => 1, 'question_id' => $questionTwoId,
        'marked' => '1', 'version' => '0', 'return_to' => 'current', 'csrf_token' => $csrfToken,
    ], [], $cookies);
    $afterMarkUnansweredSecond = $readSession($cookies['PHPSESSID']);
    if ($markUnansweredSecond['status'] !== 303
        || ($afterMarkUnansweredSecond['exam_review_flags'][0] ?? false) !== true
        || ($afterMarkUnansweredSecond['exam_review_flags'][1] ?? false) !== true
        || ($afterMarkUnansweredSecond['answers'][$questionTwoId] ?? null) !== null
        || (int) ($afterMarkUnansweredSecond['currentQuestion'] ?? -1) !== 1) {
        throw new RuntimeException('Review state for an unanswered question was not independent of saved answers and question navigation.');
    }

    $lostResponseRetry = $http->request('POST', '/quiz', [], [
        'action' => 'submit', 'question_id' => $match[1], 'answer' => 'A', 'csrf_token' => $csrfToken,
    ], [], $cookies);
    $afterRetrySession = $readSession($cookies['PHPSESSID']);
    if (!$lostResponseRetry['success']
        || !str_contains($lostResponseRetry['output'], 'Your answer for question 1 was already recorded')
        || !str_contains($lostResponseRetry['output'], 'now on question 2 of 3')
        || (int) ($afterRetrySession['currentQuestion'] ?? -1) !== 1
        || ($afterRetrySession['answers'][$match[1]] ?? null) !== 'A') {
        throw new RuntimeException('Retrying an answer after a lost response did not confirm the authoritative saved answer without advancing twice.');
    }

    $conflictingStaleRetry = $http->request('POST', '/quiz', [], [
        'action' => 'submit', 'question_id' => $match[1], 'answer' => 'B', 'csrf_token' => $csrfToken,
    ], [], $cookies);
    $afterConflictingRetry = $readSession($cookies['PHPSESSID']);
    if (!$conflictingStaleRetry['success']
        || !str_contains($conflictingStaleRetry['output'], 'The previously saved answer for question 1 was kept')
        || str_contains($conflictingStaleRetry['output'], 'Correct Answer')
        || (int) ($afterConflictingRetry['currentQuestion'] ?? -1) !== 1
        || ($afterConflictingRetry['answers'][$match[1]] ?? null) !== 'A') {
        throw new RuntimeException('A stale conflicting answer request overwrote the authoritative saved answer.');
    }

    $refreshAfterSave = $http->request('GET', '/quiz', [], [], [], $cookies);
    if (!$refreshAfterSave['success'] || !str_contains($refreshAfterSave['output'], 'Question 2 / 3')
        || !str_contains($refreshAfterSave['output'], '1 of 3 answers are currently recorded in this exam session')) {
        throw new RuntimeException('Refreshing an active exam did not show the authoritative persisted answer count.');
    }

    $questionTwoId = (string) ($afterRetrySession['questions'][1]['id'] ?? '');
    $skipSecond = $http->request('POST', '/quiz', [], [
        'action' => 'next', 'from_question_id' => $questionTwoId, 'csrf_token' => $csrfToken,
    ], [], $cookies);
    $afterSkipSecondSession = $readSession($cookies['PHPSESSID']);
    $questionThreeId = (string) ($afterSkipSecondSession['questions'][2]['id'] ?? '');
    $skipLast = $http->request('POST', '/quiz', [], [
        'action' => 'next', 'from_question_id' => $questionThreeId, 'csrf_token' => $csrfToken,
    ], [], $cookies);
    if (!$skipSecond['success'] || !str_contains($skipSecond['output'], 'Question 3 / 3')
        || $skipLast['status'] !== 303 || $skipLast['location'] !== '/quiz?action=review') {
        throw new RuntimeException('Question navigation did not preserve skips or open review at the final question.');
    }

    $review = $http->request('GET', '/quiz', ['action' => 'review'], [], [], $cookies);
    if (!$review['success'] || !str_contains($review['output'], '3 questions · 1 answer recorded · 2 unanswered · 2 marked for review.')
        || !str_contains($review['output'], '2 will be marked unanswered')
        || substr_count($review['output'], 'class="exam-review-mark-state"') !== 2
        || !str_contains($review['output'], 'pending answer is not included')
        || str_contains($review['output'], 'Correct Answer:')) {
        throw new RuntimeException('Pre-submit review did not identify unanswered questions.');
    }

    $unmarkFromReview = $http->request('POST', '/quiz', [], [
        'action' => 'review-flag', 'index' => 1, 'question_id' => $questionTwoId,
        'marked' => '0', 'version' => '1', 'return_to' => 'review', 'csrf_token' => $csrfToken,
    ], [], $cookies);
    $afterReviewUnmark = $readSession($cookies['PHPSESSID']);
    $reviewAfterUnmark = $http->request('GET', '/quiz', ['action' => 'review'], [], [], $cookies);
    if ($unmarkFromReview['status'] !== 303 || $unmarkFromReview['location'] !== '/quiz?action=review'
        || ($afterReviewUnmark['exam_review_flags'][1] ?? false) !== false
        || ($afterReviewUnmark['exam_review_flags'][0] ?? false) !== true
        || !str_contains($reviewAfterUnmark['output'], '1 marked for review')) {
        throw new RuntimeException('The pre-submit navigator could not safely remove one review mark while preserving another.');
    }

    $jumpBack = $http->request('POST', '/quiz', [], [
        'action' => 'jump', 'index' => 0, 'from_question_id' => $questionThreeId, 'csrf_token' => $csrfToken,
    ], [], $cookies);
    if (!$jumpBack['success'] || !preg_match('/name="answer"\s+value="A"\s+required\s+checked/', $jumpBack['output'])) {
        throw new RuntimeException('Returning to an answered question did not restore its saved selection.');
    }
    $changeAnswer = $http->request('POST', '/quiz', [], [
        'action' => 'submit', 'question_id' => $match[1], 'answer' => 'B', 'csrf_token' => $csrfToken,
    ], [], $cookies);
    if (!$changeAnswer['success'] || !str_contains($changeAnswer['output'], 'Question 2 / 3')) {
        throw new RuntimeException('Exam simulation did not allow a learner to change an answer before submission.');
    }

    $earlyFinish = $http->request('POST', '/quiz', [], ['action' => 'finish', 'csrf_token' => $csrfToken], [], $cookies);
    if ($earlyFinish['status'] !== 303 || $earlyFinish['location'] !== '/quiz?action=review') {
        throw new RuntimeException('The final submit action bypassed a review after the answer set changed.');
    }
    $finalReview = $http->request('GET', '/quiz', ['action' => 'review'], [], [], $cookies);
    if (!str_contains($finalReview['output'], 'B.')) {
        throw new RuntimeException('Review did not show the updated answer.');
    }

    $concurrentFinishes = $http->requestsConcurrently([
        ['method' => 'POST', 'path' => '/quiz', 'post' => ['action' => 'finish', 'csrf_token' => $csrfToken], 'cookies' => $cookies],
        ['method' => 'POST', 'path' => '/quiz', 'post' => ['action' => 'finish', 'csrf_token' => $csrfToken], 'cookies' => $cookies],
    ]);
    $finish = $concurrentFinishes[0] ?? [];
    $secondFinish = $concurrentFinishes[1] ?? [];
    if (count($concurrentFinishes) !== 2
        || ($finish['status'] ?? 0) !== 303 || ($finish['location'] ?? null) !== '/quiz?action=result'
        || ($secondFinish['status'] ?? 0) !== 303 || ($secondFinish['location'] ?? null) !== '/quiz?action=result') {
        throw new RuntimeException('Simultaneous final-submit requests did not both recover to the single persisted result.');
    }
    $finalizedStorage = [
        'attempts' => (string) file_get_contents($path),
        'weakness' => (string) file_get_contents($weaknessPath),
        'question_statistics' => (string) file_get_contents($statisticsPath),
    ];
    $lostFinalResponseRetry = $http->request('POST', '/quiz', [], ['action' => 'finish', 'csrf_token' => $csrfToken], [], $cookies);
    if ($lostFinalResponseRetry['status'] !== 303 || $lostFinalResponseRetry['location'] !== '/quiz?action=result'
        || $finalizedStorage['attempts'] !== (string) file_get_contents($path)
        || $finalizedStorage['weakness'] !== (string) file_get_contents($weaknessPath)
        || $finalizedStorage['question_statistics'] !== (string) file_get_contents($statisticsPath)) {
        throw new RuntimeException('Retrying a finalized exam after a lost response changed attempts or learner statistics.');
    }
    $result = $http->request('GET', '/quiz', ['action' => 'result'], [], [], $cookies);
    $repeat = $http->request('GET', '/quiz', ['action' => 'result'], [], [], $cookies);
    $duplicateSubmit = $http->request('POST', '/quiz', [], [
        'action' => 'submit', 'question_id' => $match[1], 'answer' => 'A', 'csrf_token' => $csrfToken,
    ], [], $cookies);
    $resultText = preg_replace('/\s+/', ' ', strip_tags($result['output'])) ?? '';
    if ($finish['status'] !== 303 || !$result['success'] || !$repeat['success']
        || $duplicateSubmit['status'] !== 302
        || !str_contains($resultText, 'Exam Simulation Result')
        || !preg_match('/Score\s*:?[\s]*\d+\s*\/\s*3/', $resultText)
        || !str_contains($resultText, 'Score percentage')
        || !str_contains($resultText, 'Question answer accuracy')
        || !preg_match('/Unanswered\s*2/', $resultText)
        || !str_contains($result['output'], 'href="/mistake-notebook"')
        || substr_count($result['output'], 'No answer') !== 2) {
        throw new RuntimeException('Partial simulation result or duplicate-submission protection failed.');
    }

    $persistedAttemptsForFilter = json_decode((string) file_get_contents($path), true);
    $completedAttemptForFilter = array_values(array_filter(
        is_array($persistedAttemptsForFilter) ? $persistedAttemptsForFilter : [],
        static fn(mixed $attempt): bool => is_array($attempt) && is_scalar($attempt['id'] ?? null)
            && !in_array((string) $attempt['id'], $beforeIds, true)
            && ($attempt['time_expired'] ?? false) !== true
    ))[0] ?? [];
    $expectedIncorrect = count(array_filter((array) ($completedAttemptForFilter['review'] ?? []),
        static fn(mixed $item): bool => is_array($item) && ($item['answered'] ?? false) === true && ($item['correct'] ?? false) !== true));
    $storageBeforeResultFilter = [
        'attempts' => (string) file_get_contents($path),
        'weakness' => (string) file_get_contents($weaknessPath),
        'question_statistics' => (string) file_get_contents($statisticsPath),
    ];
    $incorrectOnlyResult = $http->request('GET', '/quiz', ['action' => 'result', 'review_filter' => 'incorrect'], [], [], $cookies);
    $unansweredOnlyResult = $http->request('GET', '/quiz', ['action' => 'result', 'review_filter' => 'unanswered'], [], [], $cookies);
    if (!$incorrectOnlyResult['success'] || !$unansweredOnlyResult['success']
        || substr_count($incorrectOnlyResult['output'], '<article class="question-review') !== $expectedIncorrect
        || substr_count($unansweredOnlyResult['output'], '<article class="question-review') !== 2
        || !str_contains($incorrectOnlyResult['output'], 'aria-current="page"')
        || !str_contains($result['output'], 'review_filter=incorrect')
        || array_key_exists('exam_review_flags', $completedAttemptForFilter)
        || array_key_exists('exam_review_flag_versions', $completedAttemptForFilter)
        || $storageBeforeResultFilter['attempts'] !== (string) file_get_contents($path)
        || $storageBeforeResultFilter['weakness'] !== (string) file_get_contents($weaknessPath)
        || $storageBeforeResultFilter['question_statistics'] !== (string) file_get_contents($statisticsPath)) {
        throw new RuntimeException('Completed-result filters did not reflect persisted answer outcomes or changed learner history.');
    }

    $raceCookies = ['PHPSESSID' => 'batch723-answer-nav-race-' . bin2hex(random_bytes(8))];
    $raceSettings = $http->request('GET', '/quiz', [], [], [], $raceCookies);
    if (!$raceSettings['success'] || !preg_match('/name="csrf_token" value="([^"]+)"/', $raceSettings['output'], $raceCsrfMatch)) {
        throw new RuntimeException('Race fixture did not receive a CSRF token.');
    }
    $raceCsrf = $raceCsrfMatch[1];
    $raceStart = $http->request('POST', '/quiz', [], [
        'action' => 'start', 'exam' => 'LET', 'subject' => 'English', 'mode' => 'exam',
        'timer_mode' => 'untimed', 'difficulty' => 'mixed', 'count' => 2, 'csrf_token' => $raceCsrf,
    ], [], $raceCookies);
    if (!$raceStart['success'] || !preg_match('/name="question_id"\s+value="([^"]+)"/', $raceStart['output'], $raceQuestionMatch)) {
        throw new RuntimeException('Race fixture exam did not start with its first question.');
    }
    $raceSessionBefore = $readSession($raceCookies['PHPSESSID']);
    $raceQuestionId = $raceQuestionMatch[1];
    $raceNextQuestionId = (string) ($raceSessionBefore['questions'][1]['id'] ?? '');
    $raceResponses = $http->requestsConcurrently([
        ['method' => 'POST', 'path' => '/quiz', 'post' => [
            'action' => 'submit', 'question_id' => $raceQuestionId, 'answer' => 'A', 'csrf_token' => $raceCsrf,
        ], 'cookies' => $raceCookies],
        ['method' => 'POST', 'path' => '/quiz', 'post' => [
            'action' => 'next', 'from_question_id' => $raceQuestionId, 'csrf_token' => $raceCsrf,
        ], 'cookies' => $raceCookies],
        ['method' => 'POST', 'path' => '/quiz', 'post' => [
            'action' => 'review-flag', 'index' => 0, 'question_id' => $raceQuestionId,
            'marked' => '1', 'version' => '0', 'return_to' => 'current', 'csrf_token' => $raceCsrf,
        ], 'cookies' => $raceCookies],
    ]);
    $raceSessionAfter = $readSession($raceCookies['PHPSESSID']);
    if (count($raceResponses) !== 3
        || (int) ($raceSessionAfter['currentQuestion'] ?? -1) !== 1
        || (string) ($raceSessionAfter['questions'][1]['id'] ?? '') !== $raceNextQuestionId
        || ($raceSessionAfter['answers'][$raceQuestionId] ?? null) !== 'A'
        || ($raceSessionAfter['exam_review_flags'][0] ?? false) !== true
        || (int) ($raceSessionAfter['exam_review_flag_versions'][0] ?? -1) !== 1) {
        throw new RuntimeException('Concurrent answer, review mark and navigation requests lost state or advanced past the next question: ' . json_encode([
            'response_count' => count($raceResponses),
            'current' => $raceSessionAfter['currentQuestion'] ?? null,
            'answer' => $raceSessionAfter['answers'][$raceQuestionId] ?? null,
            'flags' => $raceSessionAfter['exam_review_flags'] ?? null,
            'versions' => $raceSessionAfter['exam_review_flag_versions'] ?? null,
        ]));
    }

    $answerRaceCookies = ['PHPSESSID' => 'batch723-conflicting-answer-race-' . bin2hex(random_bytes(8))];
    $answerRaceSettings = $http->request('GET', '/quiz', [], [], [], $answerRaceCookies);
    if (!$answerRaceSettings['success'] || !preg_match('/name="csrf_token" value="([^"]+)"/', $answerRaceSettings['output'], $answerRaceCsrfMatch)) {
        throw new RuntimeException('Conflicting-answer race fixture did not receive a CSRF token.');
    }
    $answerRaceCsrf = $answerRaceCsrfMatch[1];
    $answerRaceStart = $http->request('POST', '/quiz', [], [
        'action' => 'start', 'exam' => 'LET', 'subject' => 'English', 'mode' => 'exam',
        'timer_mode' => 'untimed', 'difficulty' => 'mixed', 'count' => 2, 'csrf_token' => $answerRaceCsrf,
    ], [], $answerRaceCookies);
    if (!$answerRaceStart['success'] || !preg_match('/name="question_id"\s+value="([^"]+)"/', $answerRaceStart['output'], $answerRaceQuestionMatch)) {
        throw new RuntimeException('Conflicting-answer race fixture did not start its exam.');
    }
    $answerRaceQuestionId = $answerRaceQuestionMatch[1];
    $conflictingAnswerResponses = $http->requestsConcurrently([
        ['method' => 'POST', 'path' => '/quiz', 'post' => [
            'action' => 'submit', 'question_id' => $answerRaceQuestionId, 'answer' => 'A', 'csrf_token' => $answerRaceCsrf,
        ], 'cookies' => $answerRaceCookies],
        ['method' => 'POST', 'path' => '/quiz', 'post' => [
            'action' => 'submit', 'question_id' => $answerRaceQuestionId, 'answer' => 'B', 'csrf_token' => $answerRaceCsrf,
        ], 'cookies' => $answerRaceCookies],
    ]);
    $answerRaceSession = $readSession($answerRaceCookies['PHPSESSID']);
    $authoritativeRaceAnswer = $answerRaceSession['answers'][$answerRaceQuestionId] ?? null;
    if (count($conflictingAnswerResponses) !== 2
        || !in_array($authoritativeRaceAnswer, ['A', 'B'], true)
        || (int) ($answerRaceSession['currentQuestion'] ?? -1) !== 1
        || (string) ($answerRaceSession['questions'][1]['id'] ?? '') === ''
        || str_contains(implode("\n", array_column($conflictingAnswerResponses, 'output')), 'Correct Answer')) {
        throw new RuntimeException('Nearly simultaneous conflicting answer saves overwrote the first persisted answer, leaked a key, or advanced more than once.');
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
        'csrf_token' => $csrfToken,
    ], [], $cookies);
    $retryText = preg_replace('/\s+/', ' ', strip_tags($retry['output'])) ?? '';
    if (!$retry['success'] || !str_contains($retryText, 'Mode: Practice')) {
        throw new RuntimeException('A new practice session did not replace the completed simulation.');
    }

$expiryCookies = ['PHPSESSID' => 'batch676-expired-exam-' . bin2hex(random_bytes(8))];
    $expirySettings = $http->request('GET', '/quiz', [], [], [], $expiryCookies);
    if (!$expirySettings['success'] || !preg_match('/name="csrf_token" value="([^"]+)"/', $expirySettings['output'], $expiryCsrfMatch)) {
        throw new RuntimeException('Quiz settings did not provide a CSRF token for expired-timer coverage.');
    }
    $expiryStart = $http->request('POST', '/quiz', [], [
        'action' => 'start', 'exam' => 'LET', 'subject' => 'English',
        'mode' => 'exam', 'timer_mode' => 'timed', 'time_limit_minutes' => 1,
        'difficulty' => 'mixed', 'count' => 2, 'csrf_token' => $expiryCsrfMatch[1],
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

    $expiredDashboard = $http->request('GET', '/dashboard', [], [], [], $expiryCookies);
    if (!$expiredDashboard['success'] || !str_contains($expiredDashboard['output'], 'data-active-exam-state="EXPIRED_SESSION"')
        || !str_contains($expiredDashboard['output'], 'The server deadline has passed')
        || !str_contains($expiredDashboard['output'], 'View submitted result')
        || str_contains($expiredDashboard['output'], 'Resume exam simulation')) {
        throw new RuntimeException('An expired exam was presented as resumable instead of directing the learner to server finalization.');
    }

    $expiredQuestionId = (string) ($expiredState['questions'][0]['id'] ?? '');
    $expiredReviewFlag = $http->request('POST', '/quiz', [], [
        'action' => 'review-flag', 'index' => 0, 'question_id' => $expiredQuestionId,
        'marked' => '1', 'version' => '0', 'return_to' => 'current', 'csrf_token' => $expiryCsrfMatch[1],
    ], [], $expiryCookies);
    $afterExpiredReviewFlag = $readSession($expiryCookies['PHPSESSID']);
    if (!in_array($expiredReviewFlag['status'], [302, 303], true)
        || $expiredReviewFlag['location'] !== '/quiz?action=result'
        || (array) ($afterExpiredReviewFlag['exam_review_flags'] ?? []) !== []
        || (array) ($afterExpiredReviewFlag['answers'] ?? []) !== []) {
        throw new RuntimeException('An expired exam accepted a review mark instead of preserving its deadline and saved answers.');
    }
    $lateExpiredAnswer = $http->request('POST', '/quiz', [], [
        'action' => 'submit', 'question_id' => $expiredQuestionId, 'answer' => 'A', 'csrf_token' => $expiryCsrfMatch[1],
    ], [], $expiryCookies);
    $expiredAfterLateAnswer = $readSession($expiryCookies['PHPSESSID']);
    if (!in_array($lateExpiredAnswer['status'], [302, 303], true)
        || $lateExpiredAnswer['location'] !== '/quiz?action=result'
        || (array) ($expiredAfterLateAnswer['answers'] ?? []) !== []) {
        throw new RuntimeException('An answer request processed after the server deadline was not rejected without saving.');
    }
    $expiredRefresh = $http->request('GET', '/quiz', ['action' => 'result'], [], [], $expiryCookies);
    $expiredResult = $http->request('GET', '/quiz', ['action' => 'result'], [], [], $expiryCookies);
    $expiredText = preg_replace('/\s+/', ' ', strip_tags($expiredResult['output'])) ?? '';
    $afterExpiry = json_decode((string) file_get_contents($path), true);
    $allNewAttempts = array_slice(is_array($afterExpiry) ? $afterExpiry : [], count($before));
    $expiredAttempts = array_values(array_filter(
        $allNewAttempts,
        static fn(mixed $attempt): bool => is_array($attempt) && ($attempt['time_expired'] ?? false) === true
    ));
    if ($expiredRefresh['status'] !== 200
        || !$expiredResult['success'] || !str_contains($expiredText, 'Time expired; your saved answers were submitted.')
        || count($allNewAttempts) !== 2 || count($expiredAttempts) !== 1
        || (int) ($expiredAttempts[0]['unanswered'] ?? -1) !== 2) {
        throw new RuntimeException('Refreshing after the authoritative deadline did not submit once with unanswered items retained: ' . json_encode([
            'late_status' => $lateExpiredAnswer['status'] ?? null,
            'late_location' => $lateExpiredAnswer['location'] ?? null,
            'refresh_status' => $expiredRefresh['status'] ?? null,
            'refresh_location' => $expiredRefresh['location'] ?? null,
            'result_success' => $expiredResult['success'] ?? null,
            'result_text' => substr($expiredText, 0, 350),
            'new_attempt_count' => count($allNewAttempts),
            'expired_attempt_count' => count($expiredAttempts),
            'expired_attempt' => $expiredAttempts[0] ?? null,
        ]));
    }

    echo "[PASS] Timed/untimed exam simulation setup, resume, durable review flags, stale flag rejection, answer/navigation races, editable answers, review summary, late-expiration rejection, partial scoring, history metadata, lost-response retries, and concurrent exact-once submission verified.\n";
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
