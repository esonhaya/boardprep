<?php

declare(strict_types=1);

putenv('APP_ENV=testing');
putenv('APP_DEBUG=false');
putenv('BOARDPREP_ACCOUNTS_ENABLED=true');
require_once dirname(__DIR__) . '/Doctor/Project/BoardPrep/Simulation/HttpSimulator.php';
require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

use Tools\Doctor\Project\BoardPrep\Simulation\HttpSimulator;

function durableDraftHttpAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function durableDraftHttpCsrf(string $html): string
{
    if (preg_match('/name="csrf_token" value="([^"]+)"/', $html, $match) !== 1) {
        throw new RuntimeException('The simulated form did not contain a CSRF token.');
    }
    return html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function durableDraftHttpAccountId(string $path, string $username): array
{
    $state = json_decode((string) file_get_contents($path), true, 64, JSON_THROW_ON_ERROR);
    $account = $state['accounts'][$username] ?? null;
    if (!is_array($account)) {
        throw new RuntimeException('The synthetic account record was not created.');
    }
    return [$account['account_id'], $account['learner_id']];
}

function durableDraftHttpSessionId(string $directory, string $accountId, array $exclude = []): string
{
    foreach (glob(rtrim($directory, DIRECTORY_SEPARATOR) . '/sess_*') ?: [] as $path) {
        $id = substr(basename($path), strlen('sess_'));
        if (in_array($id, $exclude, true)) {
            continue;
        }
        $contents = is_file($path) ? file_get_contents($path) : false;
        if (is_string($contents) && str_contains($contents, $accountId)) {
            return $id;
        }
    }
    throw new RuntimeException('The authenticated simulator session could not be located.');
}

function durableDraftHttpLogin(HttpSimulator $http, string $username, string $password, string $cookieId, array $exclude = []): array
{
    $cookies = ['PHPSESSID' => $cookieId];
    $page = $http->request('GET', '/account/login', [], [], [], $cookies);
    durableDraftHttpAssert($page['status'] === 200, 'The synthetic login form did not load.');
    $response = $http->request('POST', '/account/login', [], [
        'csrf_token' => durableDraftHttpCsrf($page['output']),
        'username' => $username,
        'password' => $password,
    ], [], $cookies);
    durableDraftHttpAssert($response['status'] === 303 && $response['location'] === '/account/profile', 'Synthetic account sign-in failed.');
    [$accountId, $learnerId] = durableDraftHttpAccountId($http->storagePath() . '/private/learner-accounts/accounts.json', $username);
    $authenticatedId = durableDraftHttpSessionId($http->sessionPath(), (string) $accountId, $exclude);
    return [['PHPSESSID' => $authenticatedId], (string) $accountId, (string) $learnerId];
}

$http = new HttpSimulator(dirname(__DIR__, 2) . '/public/index.php');
$storage = $http->storagePath();
$accountPath = $storage . '/private/learner-accounts/accounts.json';
$username = 'batch740-' . bin2hex(random_bytes(5));
$password = 'Batch740 HTTP synthetic password phrase';
$cookieA = ['PHPSESSID' => 'batch740-owner-' . bin2hex(random_bytes(8))];
$registerPage = $http->request('GET', '/account/register', [], [], [], $cookieA);
$register = $http->request('POST', '/account/register', [], [
    'csrf_token' => durableDraftHttpCsrf($registerPage['output']),
    'username' => $username,
    'password' => $password,
    'password_confirmation' => $password,
], [], $cookieA);
durableDraftHttpAssert($register['status'] === 200 && str_contains($register['output'], 'account was created'), 'Synthetic account registration did not complete.');
[$cookieA, $accountId, $learnerId] = durableDraftHttpLogin($http, $username, $password, $cookieA['PHPSESSID']);

$settings = $http->request('GET', '/quiz', [], [], [], $cookieA);
durableDraftHttpAssert($settings['status'] === 200, 'Signed-in exam setup did not load.');
$start = $http->request('POST', '/quiz', [], [
    'action' => 'start',
    'csrf_token' => durableDraftHttpCsrf($settings['output']),
    'exam' => 'LET',
    'subject' => '*',
    'mode' => 'exam',
    'difficulty' => 'mixed',
    'count' => '5',
    'timer_mode' => 'timed',
    'time_limit_minutes' => '60',
], [], $cookieA);
durableDraftHttpAssert($start['status'] === 200 && str_contains($start['output'], 'Save answer and continue'), 'Authenticated exam did not start through the production controller path; status=' . $start['status'] . ' location=' . ($start['location'] ?? '') . ' stderr=' . substr((string) $start['stderr'], 0, 500));

$draftPath = $storage . '/exam-drafts.json';
$draftRows = json_decode((string) file_get_contents($draftPath), true, 128, JSON_THROW_ON_ERROR);
durableDraftHttpAssert(is_array($draftRows) && count($draftRows) === 1, 'Exam start did not create one durable draft.');
$draft = $draftRows[0];
$draftId = (string) ($draft['id'] ?? '');
$originalDeadline = $draft['quiz_session']['deadline_epoch'] ?? null;
durableDraftHttpAssert(($draft['user_id'] ?? null) === $learnerId
    && preg_match('/\Aexam-draft-[a-f0-9]{32}\z/', $draftId) === 1
    && is_int($originalDeadline), 'Draft was not bound to the verified account or server deadline.');
$draftJson = json_encode($draft, JSON_THROW_ON_ERROR);
durableDraftHttpAssert(!str_contains($draftJson, '"answer"')
    && !str_contains($draftJson, '"explanation"')
    && !str_contains($draftJson, '"question"')
    && !str_contains($draftJson, '"choices"'), 'Draft storage contains question content or answer-key fields.');

if (preg_match('/name="question_id"\s+value="([^"]+)"/', $start['output'], $questionMatch) !== 1
    || preg_match('/name="quiz_session_id"\s+value="([^"]+)"/', $start['output'], $sessionMatch) !== 1
    || preg_match('/name="draft_revision"\s+value="([^"]*)"/', $start['output'], $revisionMatch) !== 1) {
    throw new RuntimeException('The active authenticated exam form omitted its server session or revision binding.');
}
$firstQuestionId = html_entity_decode($questionMatch[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
$oldQuizSessionId = html_entity_decode($sessionMatch[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
$oldRevision = html_entity_decode($revisionMatch[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
$submit = $http->request('POST', '/quiz', [], [
    'action' => 'submit',
    'csrf_token' => durableDraftHttpCsrf($start['output']),
    'quiz_session_id' => $oldQuizSessionId,
    'draft_revision' => $oldRevision,
    'question_id' => $firstQuestionId,
    'answer' => 'A',
], [], $cookieA);
durableDraftHttpAssert($submit['status'] === 200 && str_contains($submit['output'], 'Question 2 / 5'), 'The answer did not save and advance through the existing quiz flow.');
$draftRows = json_decode((string) file_get_contents($draftPath), true, 128, JSON_THROW_ON_ERROR);
$draft = $draftRows[0] ?? [];
durableDraftHttpAssert(($draft['answers'][$firstQuestionId] ?? null) === 'A'
    && ($draft['current_question'] ?? null) === 1
    && ($draft['revision'] ?? null) >= 2, 'Answer and navigation state were not durably saved before the response.');
$revisionAfterSave = (int) $draft['revision'];
$duplicateSave = $http->request('POST', '/quiz', [], [
    'action' => 'submit',
    'csrf_token' => durableDraftHttpCsrf($start['output']),
    'quiz_session_id' => $oldQuizSessionId,
    'draft_revision' => $oldRevision,
    'question_id' => $firstQuestionId,
    'answer' => 'A',
], [], $cookieA);
$draftRowsAfterDuplicate = json_decode((string) file_get_contents($draftPath), true, 128, JSON_THROW_ON_ERROR);
$draftAfterDuplicate = $draftRowsAfterDuplicate[0] ?? [];
durableDraftHttpAssert($duplicateSave['status'] === 200
    && str_contains($duplicateSave['output'], 'already recorded')
    && ($draftAfterDuplicate['revision'] ?? null) === $revisionAfterSave
    && ($draftAfterDuplicate['answers'][$firstQuestionId] ?? null) === 'A',
    'Retrying a committed answer changed its state or falsely repeated the write.');
$setupWhileActive = $http->request('GET', '/quiz?action=start', [], [], [], $cookieA);
$duplicateStart = $http->request('POST', '/quiz', [], [
    'action' => 'start',
    'csrf_token' => durableDraftHttpCsrf($setupWhileActive['output']),
    'exam' => 'LET',
    'subject' => '*',
    'mode' => 'exam',
    'difficulty' => 'mixed',
    'count' => '5',
    'timer_mode' => 'timed',
    'time_limit_minutes' => '60',
], [], $cookieA);
$draftRowsAfterDuplicateStart = json_decode((string) file_get_contents($draftPath), true, 128, JSON_THROW_ON_ERROR);
durableDraftHttpAssert($duplicateStart['status'] === 303
    && ($duplicateStart['location'] ?? '') === '/quiz?action=start'
    && count($draftRowsAfterDuplicateStart) === 1
    && ($draftRowsAfterDuplicateStart[0]['id'] ?? null) === $draftId,
    'Starting another exam created a duplicate attempt while a durable draft was active.');

// Lose the PHP session cookie and data, then authenticate again as the same owner.
$lostSessionId = 'batch740-lost-' . bin2hex(random_bytes(8));
$loginPage = $http->request('GET', '/account/login', [], [], [], ['PHPSESSID' => $lostSessionId]);
durableDraftHttpAssert($loginPage['status'] === 200, 'The post-loss sign-in page did not load.');
$login = $http->request('POST', '/account/login', [], [
    'csrf_token' => durableDraftHttpCsrf($loginPage['output']),
    'username' => $username,
    'password' => $password,
], [], ['PHPSESSID' => $lostSessionId]);
durableDraftHttpAssert($login['status'] === 303, 'The owner could not authenticate after PHP session loss.');
$cookieAfterLoss = ['PHPSESSID' => durableDraftHttpSessionId($http->sessionPath(), $accountId, [$cookieA['PHPSESSID'], $lostSessionId])];
$setupAfterLoss = $http->request('GET', '/quiz', ['action' => 'start'], [], [], $cookieAfterLoss);
durableDraftHttpAssert($setupAfterLoss['status'] === 200 && str_contains($setupAfterLoss['output'], 'Resume saved exam'), 'The owned durable draft was not offered after reauthentication.');

$wrongCsrf = $http->request('POST', '/quiz', [], [
    'action' => 'recover-draft',
    'csrf_token' => 'invalid-token',
], [], $cookieAfterLoss);
durableDraftHttpAssert(in_array($wrongCsrf['status'], [302, 303], true)
    && ($wrongCsrf['location'] ?? '') === '/quiz', 'Draft recovery accepted a missing or invalid CSRF token.');
$recoveryCsrf = durableDraftHttpCsrf($setupAfterLoss['output']);
$concurrentRecoveries = $http->requestsConcurrently([
    ['method' => 'POST', 'path' => '/quiz', 'post' => ['action' => 'recover-draft', 'csrf_token' => $recoveryCsrf], 'cookies' => $cookieAfterLoss],
    ['method' => 'POST', 'path' => '/quiz', 'post' => ['action' => 'recover-draft', 'csrf_token' => $recoveryCsrf], 'cookies' => $cookieAfterLoss],
]);
durableDraftHttpAssert(count($concurrentRecoveries) === 2
    && $concurrentRecoveries[0]['status'] === 303 && ($concurrentRecoveries[0]['location'] ?? '') === '/quiz'
    && $concurrentRecoveries[1]['status'] === 303 && ($concurrentRecoveries[1]['location'] ?? '') === '/quiz',
    'Simultaneous owner recovery requests did not complete through the authenticated controller path.');
$recoveredPage = $http->request('GET', '/quiz', [], [], [], $cookieAfterLoss);
durableDraftHttpAssert($recoveredPage['status'] === 200
    && str_contains($recoveredPage['output'], 'Question 2 / 5')
    && str_contains($recoveredPage['output'], 'Time remaining'), 'Recovered exam did not restore its question position or timer state.');
if (preg_match('/name="quiz_session_id"\s+value="([^"]+)"/', $recoveredPage['output'], $newSessionMatch) !== 1
    || preg_match('/name="draft_revision"\s+value="([^"]*)"/', $recoveredPage['output'], $newRevisionMatch) !== 1) {
    throw new RuntimeException('Recovered exam omitted fresh session or revision bindings.');
}
$newQuizSessionId = html_entity_decode($newSessionMatch[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
$newRevision = html_entity_decode($newRevisionMatch[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
$draftRowsAfterConcurrentRecovery = json_decode((string) file_get_contents($draftPath), true, 128, JSON_THROW_ON_ERROR);
$draftAfterConcurrentRecovery = $draftRowsAfterConcurrentRecovery[0] ?? [];
durableDraftHttpAssert($newQuizSessionId !== $oldQuizSessionId
    && (int) $newRevision >= (int) $draft['revision'] + 2
    && ($draftAfterConcurrentRecovery['active_quiz_session_id'] ?? null) === $newQuizSessionId
    && ($draftAfterConcurrentRecovery['revision'] ?? null) === (int) $newRevision,
    'Concurrent recovery reused an epoch, lost a revision update, or left the rendered session stale.');
$reviewAfterRecovery = $http->request('GET', '/quiz', ['action' => 'review'], [], [], $cookieAfterLoss);
durableDraftHttpAssert($reviewAfterRecovery['status'] === 200
    && str_contains($reviewAfterRecovery['output'], '1 answer recorded')
    && str_contains($reviewAfterRecovery['output'], 'Answered'),
    'The reloaded server-persisted answer was not restored to the review screen.');

$stalePost = $http->request('POST', '/quiz', [], [
    'action' => 'submit',
    'csrf_token' => durableDraftHttpCsrf($recoveredPage['output']),
    'quiz_session_id' => $oldQuizSessionId,
    'draft_revision' => $oldRevision,
    'question_id' => $firstQuestionId,
    'answer' => 'B',
], [], $cookieAfterLoss);
durableDraftHttpAssert($stalePost['status'] === 303 && ($stalePost['location'] ?? '') === '/quiz', 'A stale pre-recovery form mutated the recovered exam.');
$draftRowsAfterStale = json_decode((string) file_get_contents($draftPath), true, 128, JSON_THROW_ON_ERROR);
durableDraftHttpAssert(($draftRowsAfterStale[0]['answers'][$firstQuestionId] ?? null) === 'A', 'A stale form overwrote the already persisted answer.');

// A different authenticated owner receives no indication that this draft exists.
$usernameB = 'batch740-' . bin2hex(random_bytes(5));
$passwordB = 'Batch740 second synthetic password phrase';
$cookieB = ['PHPSESSID' => 'batch740-other-' . bin2hex(random_bytes(8))];
$registerPageB = $http->request('GET', '/account/register', [], [], [], $cookieB);
$registerB = $http->request('POST', '/account/register', [], [
    'csrf_token' => durableDraftHttpCsrf($registerPageB['output']),
    'username' => $usernameB,
    'password' => $passwordB,
    'password_confirmation' => $passwordB,
], [], $cookieB);
durableDraftHttpAssert($registerB['status'] === 200, 'Second synthetic account registration failed.');
[$cookieB, $accountIdB, $learnerIdB] = durableDraftHttpLogin($http, $usernameB, $passwordB, $cookieB['PHPSESSID']);
$setupB = $http->request('GET', '/quiz', [], [], [], $cookieB);
durableDraftHttpAssert(!str_contains($setupB['output'], $draftId)
    && !str_contains($setupB['output'], 'Resume saved exam'), 'Another account could discover the owner’s unfinished exam.');
$foreignRecovery = $http->request('POST', '/quiz', [], [
    'action' => 'recover-draft',
    'csrf_token' => durableDraftHttpCsrf($setupB['output']),
], [], $cookieB);
durableDraftHttpAssert($foreignRecovery['status'] === 303, 'A cross-account recovery request was not rejected.');
$draftRowsAfterForeign = json_decode((string) file_get_contents($draftPath), true, 128, JSON_THROW_ON_ERROR);
durableDraftHttpAssert(count($draftRowsAfterForeign) === 1
    && ($draftRowsAfterForeign[0]['user_id'] ?? null) === $learnerId
    && ($draftRowsAfterForeign[0]['quiz_session']['deadline_epoch'] ?? null) === $originalDeadline,
    'Cross-account recovery changed or extended the owner’s draft.');

// The rightful owner can finish once; retries resolve to the same result and delete the draft.
$review = $http->request('GET', '/quiz', ['action' => 'review'], [], [], $cookieAfterLoss);
durableDraftHttpAssert($review['status'] === 200, 'The recovered exam review screen did not load.');
$finish = $http->request('POST', '/quiz', [], [
    'action' => 'finish',
    'csrf_token' => durableDraftHttpCsrf($review['output']),
    'quiz_session_id' => $newQuizSessionId,
    'draft_revision' => $newRevision,
], [], $cookieAfterLoss);
durableDraftHttpAssert($finish['status'] === 303 && ($finish['location'] ?? '') === '/quiz?action=result',
    'The recovered attempt did not finalize through the existing result path: ' . json_encode([
        'status' => $finish['status'], 'location' => $finish['location'],
        'message' => substr(strip_tags($finish['output']), 0, 240), 'stderr' => substr($finish['stderr'], 0, 500),
    ], JSON_UNESCAPED_SLASHES));
$attemptRows = json_decode((string) file_get_contents($storage . '/attempts.json'), true, 128, JSON_THROW_ON_ERROR);
$matchingAttempts = array_values(array_filter($attemptRows, static fn(array $attempt): bool => ($attempt['session_id'] ?? null) === $draftId && ($attempt['user_id'] ?? null) === $learnerId));
$draftRowsAfterFinish = json_decode((string) file_get_contents($draftPath), true, 128, JSON_THROW_ON_ERROR);
durableDraftHttpAssert(count($matchingAttempts) === 1 && ($matchingAttempts[0]['completed'] ?? false) === true
    && !in_array($draftId, array_column($draftRowsAfterFinish, 'id'), true), 'Finalization duplicated a result or retained a submitted draft.');

$finishRetry = $http->request('POST', '/quiz', [], [
    'action' => 'finish',
    'csrf_token' => durableDraftHttpCsrf($review['output']),
    'quiz_session_id' => $newQuizSessionId,
    'draft_revision' => $newRevision,
], [], $cookieAfterLoss);
$attemptRowsAfterRetry = json_decode((string) file_get_contents($storage . '/attempts.json'), true, 128, JSON_THROW_ON_ERROR);
$matchingAfterRetry = array_values(array_filter($attemptRowsAfterRetry, static fn(array $attempt): bool => ($attempt['session_id'] ?? null) === $draftId && ($attempt['user_id'] ?? null) === $learnerId));
durableDraftHttpAssert($finishRetry['status'] === 303 && count($matchingAfterRetry) === 1, 'A repeated finish duplicated or changed the durable result.');

// A durable answer mutation and finalization must serialize to one immutable result.
$raceSettings = $http->request('GET', '/quiz?action=start', [], [], [], $cookieAfterLoss);
$raceStart = $http->request('POST', '/quiz', [], [
    'action' => 'start',
    'csrf_token' => durableDraftHttpCsrf($raceSettings['output']),
    'exam' => 'LET',
    'subject' => '*',
    'mode' => 'exam',
    'difficulty' => 'mixed',
    'count' => '5',
    'timer_mode' => 'untimed',
], [], $cookieAfterLoss);
durableDraftHttpAssert($raceStart['status'] === 200
    && preg_match('/name="question_id"\s+value="([^"]+)"/', $raceStart['output'], $raceQuestionMatch) === 1
    && preg_match('/name="quiz_session_id"\s+value="([^"]+)"/', $raceStart['output'], $raceSessionMatch) === 1
    && preg_match('/name="draft_revision"\s+value="([^"]*)"/', $raceStart['output'], $raceRevisionMatch) === 1,
    'The durable save/finalization race fixture did not start with server bindings.');
$raceQuestionId = html_entity_decode($raceQuestionMatch[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
$raceQuizSessionId = html_entity_decode($raceSessionMatch[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
$raceDraftRevision = html_entity_decode($raceRevisionMatch[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
$raceCsrf = durableDraftHttpCsrf($raceStart['output']);
$raceDraftRows = json_decode((string) file_get_contents($draftPath), true, 128, JSON_THROW_ON_ERROR);
$raceDraft = $raceDraftRows[0] ?? [];
$raceDraftId = (string) ($raceDraft['id'] ?? '');
durableDraftHttpAssert($raceDraftId !== '' && ($raceDraft['user_id'] ?? null) === $learnerId,
    'The race fixture draft was not owned by its authenticated learner.');
$raceReview = $http->request('GET', '/quiz', ['action' => 'review'], [], [], $cookieAfterLoss);
durableDraftHttpAssert($raceReview['status'] === 200, 'The race fixture review state could not be prepared.');
$raceResponses = $http->requestsConcurrently([
    ['method' => 'POST', 'path' => '/quiz', 'post' => [
        'action' => 'submit',
        'quiz_session_id' => $raceQuizSessionId,
        'draft_revision' => $raceDraftRevision,
        'question_id' => $raceQuestionId,
        'answer' => 'A',
        'csrf_token' => $raceCsrf,
    ], 'cookies' => $cookieAfterLoss],
    ['method' => 'POST', 'path' => '/quiz', 'post' => [
        'action' => 'finish',
        'quiz_session_id' => $raceQuizSessionId,
        'draft_revision' => $raceDraftRevision,
        'csrf_token' => $raceCsrf,
    ], 'cookies' => $cookieAfterLoss],
]);
durableDraftHttpAssert(count($raceResponses) === 2,
    'The durable answer save/finalization race did not return both simulated requests.');
$raceAttempts = static function () use ($storage, $raceDraftId, $learnerId): array {
    $rows = json_decode((string) file_get_contents($storage . '/attempts.json'), true, 128, JSON_THROW_ON_ERROR);
    return array_values(array_filter($rows, static fn(array $attempt): bool =>
        ($attempt['session_id'] ?? null) === $raceDraftId
        && ($attempt['user_id'] ?? null) === $learnerId
    ));
};
$raceFinalAttempts = $raceAttempts();
if ($raceFinalAttempts === []) {
    // If the answer won first, it invalidates the old review revision; review the latest state and finish it.
    $raceReviewAfterSave = $http->request('GET', '/quiz', ['action' => 'review'], [], [], $cookieAfterLoss);
    durableDraftHttpAssert($raceReviewAfterSave['status'] === 200
        && preg_match('/name="quiz_session_id"\s+value="([^"]+)"/', $raceReviewAfterSave['output'], $raceFinalSessionMatch) === 1
        && preg_match('/name="draft_revision"\s+value="([^"]*)"/', $raceReviewAfterSave['output'], $raceFinalRevisionMatch) === 1,
        'The durable race did not leave an active, reviewable attempt after a save-first ordering.');
    $raceFinalized = $http->request('POST', '/quiz', [], [
        'action' => 'finish',
        'csrf_token' => durableDraftHttpCsrf($raceReviewAfterSave['output']),
        'quiz_session_id' => html_entity_decode($raceFinalSessionMatch[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'),
        'draft_revision' => html_entity_decode($raceFinalRevisionMatch[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'),
    ], [], $cookieAfterLoss);
    durableDraftHttpAssert($raceFinalized['status'] === 303
        && ($raceFinalized['location'] ?? '') === '/quiz?action=result',
        'The draft could not be finalized after resolving a save-first race.');
    $raceFinalAttempts = $raceAttempts();
}
$raceDraftRowsAfterFinish = json_decode((string) file_get_contents($draftPath), true, 128, JSON_THROW_ON_ERROR);
durableDraftHttpAssert(count($raceFinalAttempts) === 1
    && ($raceFinalAttempts[0]['completed'] ?? false) === true
    && count(array_filter($raceDraftRowsAfterFinish, static fn(array $row): bool => ($row['id'] ?? null) === $raceDraftId)) === 0,
    'A durable answer/finalization race duplicated a result or left the submitted draft recoverable.');
$raceResultSnapshot = (string) file_get_contents($storage . '/attempts.json');
$lateRaceSave = $http->request('POST', '/quiz', [], [
    'action' => 'submit',
    'quiz_session_id' => $raceQuizSessionId,
    'draft_revision' => $raceDraftRevision,
    'question_id' => $raceQuestionId,
    'answer' => 'B',
    'csrf_token' => $raceCsrf,
], [], $cookieAfterLoss);
durableDraftHttpAssert(in_array($lateRaceSave['status'], [302, 303], true)
    && (string) file_get_contents($storage . '/attempts.json') === $raceResultSnapshot
    && count($raceAttempts()) === 1,
    'A late durable answer save changed or duplicated a finalized result.');

// Corrupt draft data is shown as unavailable and can be discarded by its owner.
$settingsAfterFinish = $http->request('GET', '/quiz', ['action' => 'start'], [], [], $cookieAfterLoss);
$startAgain = $http->request('POST', '/quiz', [], [
    'action' => 'start',
    'csrf_token' => durableDraftHttpCsrf($settingsAfterFinish['output']),
    'exam' => 'LET',
    'subject' => '*',
    'mode' => 'exam',
    'difficulty' => 'mixed',
    'count' => '5',
    'timer_mode' => 'timed',
    'time_limit_minutes' => '60',
], [], $cookieAfterLoss);
durableDraftHttpAssert($startAgain['status'] === 200, 'A new synthetic exam could not start after the previous result finalized.');
$draftRows = json_decode((string) file_get_contents($draftPath), true, 128, JSON_THROW_ON_ERROR);
$corruptDraft = $draftRows[0] ?? [];
$corruptDraftId = (string) ($corruptDraft['id'] ?? '');
$corruptDraft['question_ids'] = 'corrupt synthetic fixture';
file_put_contents($draftPath, json_encode([$corruptDraft], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
$unavailablePage = $http->request('GET', '/quiz', ['action' => 'start'], [], [], $cookieAfterLoss);
durableDraftHttpAssert($unavailablePage['status'] === 200
    && str_contains($unavailablePage['output'], 'saved exam cannot be verified')
    && !str_contains($unavailablePage['output'], 'Resume saved exam'),
    'Corrupt owner draft data crashed setup or was offered for recovery: ' . json_encode([
        'status' => $unavailablePage['status'],
        'unavailable_copy' => str_contains($unavailablePage['output'], 'saved exam cannot be verified'),
        'resume_copy' => str_contains($unavailablePage['output'], 'Resume saved exam'),
        'body' => substr(strip_tags($unavailablePage['output']), 0, 360),
        'stderr' => substr($unavailablePage['stderr'], 0, 500),
    ], JSON_UNESCAPED_SLASHES));
$discard = $http->request('POST', '/quiz', [], [
    'action' => 'discard-draft',
    'draft_id' => $corruptDraftId,
    'csrf_token' => durableDraftHttpCsrf($unavailablePage['output']),
], [], $cookieAfterLoss);
$draftRowsAfterDiscard = json_decode((string) file_get_contents($draftPath), true, 128, JSON_THROW_ON_ERROR);
durableDraftHttpAssert($discard['status'] === 303 && $draftRowsAfterDiscard === [],
    'The owner could not discard a corrupt saved exam through the production controller path.');

$guestCookie = ['PHPSESSID' => 'batch740-guest-' . bin2hex(random_bytes(8))];
$guestSettings = $http->request('GET', '/quiz', [], [], [], $guestCookie);
durableDraftHttpAssert($guestSettings['status'] === 200
    && str_contains($guestSettings['output'], 'current server session')
    && !str_contains($guestSettings['output'], 'Resume saved exam'),
    'Guest setup implied durable recovery or omitted the session-loss limitation.');
$guestStart = $http->request('POST', '/quiz', [], [
    'action' => 'start',
    'csrf_token' => durableDraftHttpCsrf($guestSettings['output']),
    'exam' => 'LET',
    'subject' => '*',
    'mode' => 'exam',
    'difficulty' => 'mixed',
    'count' => '5',
    'timer_mode' => 'timed',
    'time_limit_minutes' => '60',
], [], $guestCookie);
$draftRowsAfterGuestStart = json_decode((string) file_get_contents($draftPath), true, 128, JSON_THROW_ON_ERROR);
durableDraftHttpAssert($guestStart['status'] === 200
    && str_contains($guestStart['output'], 'Save answer and continue')
    && $draftRowsAfterGuestStart === [],
    'Guest exam behavior unexpectedly created or promised an account-owned recovery draft.');

echo "[PASS] authenticated and guest exam starts, duplicate-attempt prevention, answer persistence and retry idempotency, PHP session loss, reauthentication, CSRF, concurrent owner-scoped recovery, stale-form rejection, original deadline, answer restoration, cross-account isolation, save/finalization concurrency, exactly-once result persistence, and safe corrupt-draft discard.\n";
