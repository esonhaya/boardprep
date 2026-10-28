<?php

declare(strict_types=1);

require_once __DIR__ . '/Support/StaticStorageFixture.php';
$root = dirname(__DIR__, 2);
$fixture = StaticStorageFixture::create($root, 'batch712-mistake-recovery');
register_shutdown_function(static function () use ($fixture): void {
    $real = realpath($fixture);
    $tmp = realpath(sys_get_temp_dir());
    if (!is_string($real) || !is_string($tmp) || !str_starts_with($real, $tmp . DIRECTORY_SEPARATOR)
        || !str_contains(basename($real), 'boardprep-batch712-mistake-recovery-')) return;
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($real, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($iterator as $entry) {
        if ($entry->isDir() && !$entry->isLink()) @rmdir($entry->getPathname());
        else @unlink($entry->getPathname());
    }
    @rmdir($real);
});

foreach ([
    'APP_ENV' => 'production', 'APP_DEBUG' => 'false', 'APP_TIMEZONE' => 'Asia/Manila',
    'DB_DRIVER' => 'json', 'APP_STORAGE_PATH' => $fixture,
    'APP_SESSION_PATH' => $fixture . '/sessions', 'BOARDPREP_ACCOUNTS_ENABLED' => 'false',
] as $key => $value) putenv($key . '=' . $value);
$_ENV['APP_STORAGE_PATH'] = $fixture;
@mkdir($fixture . '/sessions', 0700, true);
require_once $root . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();
\App\Core\App::boot();
require_once dirname(__DIR__) . '/Doctor/Project/BoardPrep/Simulation/HttpSimulator.php';

function mistakeAssert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function mistakeAttemptsHash(string $fixture): string
{
    $path = rtrim($fixture, DIRECTORY_SEPARATOR) . '/attempts.json';
    return is_file($path) ? hash_file('sha256', $path) : hash('sha256', "missing attempts collection\n");
}

function mistakeHttpOk(array $response, string $name, int $status = 200): void
{
    if (!$response['success'] || $response['status'] !== $status) {
        throw new RuntimeException($name . ' failed: HTTP ' . $response['status'] . ', exit ' . $response['exitCode']
            . ', ' . substr(preg_replace('/\s+/', ' ', strip_tags((string) $response['output'])) ?? '', 0, 300)
            . ', ' . substr((string) $response['stderr'], -800));
    }
}

function mistakeCsrf(string $html): string
{
    if (!preg_match('/name="csrf_token"\s+value="([a-f0-9]{64})"/', $html, $match)) {
        throw new RuntimeException('A learner form did not provide its CSRF token.');
    }
    return $match[1];
}

function wrongChoice(array $question): string
{
    foreach (array_values((array) ($question['choices'] ?? [])) as $index => $_) {
        $code = chr(65 + $index);
        if (!QuestionScoreEvaluator::evaluate($question, $code)['correct']) return $code;
    }
    throw new RuntimeException('A test question did not contain a selectable incorrect choice.');
}

function correctChoice(array $question): string
{
    foreach (array_values((array) ($question['choices'] ?? [])) as $index => $_) {
        $code = chr(65 + $index);
        if (QuestionScoreEvaluator::evaluate($question, $code)['correct']) return $code;
    }
    throw new RuntimeException('A test question did not contain a selectable correct choice.');
}

function completeSingleQuestion(\Tools\Doctor\Project\BoardPrep\Simulation\HttpSimulator $http, array $cookies, string $questionId, string $csrf, bool $correct): array
{
    $question = \App\Core\App::container()->get(\App\Repositories\QuestionRepository::class)->find($questionId);
    if (!is_array($question)) throw new RuntimeException('Selected test question is not canonical.');
    $answer = $correct ? correctChoice($question) : wrongChoice($question);
    $submit = $http->request('POST', '/quiz', [], [
        'action' => 'submit', 'question_id' => $questionId, 'answer' => $answer, 'csrf_token' => $csrf,
    ], [], $cookies);
    mistakeHttpOk($submit, 'Submit test answer');
    $finish = $http->request('POST', '/quiz', [], ['action' => 'finish', 'csrf_token' => $csrf], [], $cookies);
    mistakeHttpOk($finish, 'Finish test quiz', 303);
    $result = $http->request('GET', '/quiz', ['action' => 'result'], [], [], $cookies);
    mistakeHttpOk($result, 'Open test quiz result');
    return $result;
}

$questionPath = $fixture . '/questions.json';
$canonicalHashBefore = hash_file('sha256', $questionPath);
$canonicalRows = json_decode((string) file_get_contents($questionPath), true, 512, JSON_THROW_ON_ERROR);
$questionRepo = \App\Core\App::container()->get(\App\Repositories\QuestionRepository::class);
$eligibility = \App\Services\Question\QuestionEligibilityService::index();
$eligibleRows = $questionRepo->all();
$groups = [];
foreach ($eligibleRows as $question) {
    if (!is_array($question) || !in_array(strtolower((string) ($question['status'] ?? 'active')), ['active', 'approved'], true)) continue;
    $taxonomy = is_array($question['taxonomy'] ?? null) ? $question['taxonomy'] : [];
    $topic = is_scalar($taxonomy['topic_id'] ?? null) ? trim((string) $taxonomy['topic_id']) : '';
    if ($topic === '') continue;
    $placement = \App\Services\Question\QuestionEligibilityService::forExam($question, 'let', $eligibility);
    $subject = is_array($placement) ? trim((string) ($placement['subject_id'] ?? '')) : '';
    if ($subject === '') continue;
    $groups[$subject . "\0" . $topic]['subject'] = $subject;
    $groups[$subject . "\0" . $topic]['topic'] = $topic;
    $groups[$subject . "\0" . $topic]['questions'][] = $question;
}
$target = null;
foreach ($groups as $group) {
    $request = new SelectionRequest(
        subject: $group['subject'], domain: null, difficultyDistribution: [],
        questionCount: count($group['questions']), topic: $group['topic'], exam: 'let'
    );
    $valid = QuestionPoolFilter::filter($group['questions'], $request, $eligibility);
    if (count($valid) >= 2) {
        $target = ['subject' => $group['subject'], 'topic' => $group['topic'], 'question' => $valid[0]];
        break;
    }
}
mistakeAssert(is_array($target), 'Could not find an existing LET topic with at least two eligible questions for a recovery fixture.');

$http = new \Tools\Doctor\Project\BoardPrep\Simulation\HttpSimulator($root . '/public/index.php');
$performanceMs = [];
$cookies = ['PHPSESSID' => 'batch712-learner-' . bin2hex(random_bytes(8))];
$settings = $http->request('GET', '/quiz', [
    'action' => 'start', 'exam' => 'let', 'subject' => $target['subject'], 'topic' => $target['topic'],
    'count' => 1, 'mode' => 'practice', 'difficulty' => 'mixed',
], [], [], $cookies);
mistakeHttpOk($settings, 'Open regular quiz setup');
$csrf = mistakeCsrf($settings['output']);
$start = $http->request('POST', '/quiz', [], [
    'action' => 'start', 'exam' => 'let', 'subject' => $target['subject'], 'topic' => $target['topic'],
    'count' => 1, 'mode' => 'practice', 'difficulty' => 'mixed', 'csrf_token' => $csrf,
], [], $cookies);
mistakeHttpOk($start, 'Start ordinary practice');
if (!preg_match('/name="question_id"\s+value="([^"]+)"/', $start['output'], $questionMatch)) {
    throw new RuntimeException('Ordinary quiz did not render a canonical question.');
}
$ordinaryQuestionId = $questionMatch[1];
$canonicalOrdinary = $questionRepo->find($ordinaryQuestionId);
mistakeAssert(is_array($canonicalOrdinary), 'The ordinary quiz selected a question outside canonical storage.');

// An old recovery quiz form cannot submit after its isolated session expires.
$staleCookies = ['PHPSESSID' => 'batch712-stale-' . bin2hex(random_bytes(8))];
$staleSettings = $http->request('GET', '/quiz', ['action' => 'start'], [], [], $staleCookies);
mistakeHttpOk($staleSettings, 'Open a separate stale-session quiz setup');
$staleStart = $http->request('POST', '/quiz', [], [
    'action' => 'start', 'exam' => 'let', 'subject' => $target['subject'], 'topic' => $target['topic'],
    'count' => 1, 'mode' => 'practice', 'difficulty' => 'mixed', 'csrf_token' => mistakeCsrf($staleSettings['output']),
], [], $staleCookies);
mistakeHttpOk($staleStart, 'Start a separate quiz for stale-session coverage');
if (!preg_match('/name="question_id"\s+value="([^"]+)"/', $staleStart['output'], $staleQuestionMatch)) {
    throw new RuntimeException('The stale-session quiz did not render a question.');
}
$staleSessionFile = rtrim($http->sessionPath(), DIRECTORY_SEPARATOR) . '/sess_' . $staleCookies['PHPSESSID'];
mistakeAssert(is_file($staleSessionFile) && unlink($staleSessionFile), 'The test could not expire its own isolated quiz session.');
$freshSettings = $http->request('GET', '/quiz', [], [], [], $staleCookies);
mistakeHttpOk($freshSettings, 'Open quiz settings after the session expired');
$attemptsBeforeStaleSubmit = mistakeAttemptsHash($fixture);
$staleSubmit = $http->request('POST', '/quiz', [], [
    'action' => 'submit', 'question_id' => $staleQuestionMatch[1], 'answer' => 'A',
    'csrf_token' => mistakeCsrf($freshSettings['output']),
], [], $staleCookies);
mistakeHttpOk($staleSubmit, 'Reject an answer from an expired quiz session', 302);
mistakeAssert(mistakeAttemptsHash($fixture) === $attemptsBeforeStaleSubmit,
    'An expired quiz session wrote a new attempt or altered history.');

$wrongResult = completeSingleQuestion($http, $cookies, $ordinaryQuestionId, mistakeCsrf($start['output']), false);
mistakeAssert(str_contains($wrongResult['output'], 'Incorrect'), 'Ordinary quiz answer was not graded through the production scorer.');

$attemptRows = json_decode((string) file_get_contents($fixture . '/attempts.json'), true, 512, JSON_THROW_ON_ERROR);
$firstAttempt = end($attemptRows);
mistakeAssert(is_array($firstAttempt) && is_array($firstAttempt['review'] ?? null), 'Ordinary quiz attempt was not persisted.');
$learnerId = (string) ($firstAttempt['user_id'] ?? '');
mistakeAssert(preg_match('/\Alearner-[a-f0-9]{32}\z/', $learnerId) === 1, 'Ordinary attempt did not retain the private learner identity.');

$notebook = $http->request('GET', '/mistake-notebook', ['board' => 'let', 'subject' => $target['subject']], [], [], $cookies);
$performanceMs['notebook_initial_load'] = round($notebook['duration'] * 1000, 2);
mistakeHttpOk($notebook, 'Open Mistake Notebook');
mistakeAssert(str_contains($notebook['output'], 'Mistake Notebook')
    && str_contains($notebook['output'], 'Question ' . $ordinaryQuestionId)
    && str_contains($notebook['output'], 'Not yet retried'), 'The ordinary quiz mistake did not appear as a question-level notebook entry.');
$topicNotebook = $http->request('GET', '/mistake-notebook', [
    'board' => 'let', 'subject' => $target['subject'], 'topic' => $target['topic'],
], [], [], $cookies);
$performanceMs['board_subject_topic_filter'] = round($topicNotebook['duration'] * 1000, 2);
mistakeHttpOk($topicNotebook, 'Filter Mistake Notebook to an exact weak topic');
mistakeAssert(str_contains($topicNotebook['output'], 'Weak areas in this view')
    && str_contains($topicNotebook['output'], 'name="topic"')
    && str_contains($topicNotebook['output'], 'Question ' . $ordinaryQuestionId), 'Topic weak-area summary/filter was not reflected in the notebook.');
$attemptsBeforeBadCsrf = mistakeAttemptsHash($fixture);
$badCsrfPractice = $http->request('POST', '/mistake-notebook/practice', [], [
    'csrf_token' => 'forged-csrf-token', 'board' => 'let', 'subject' => $target['subject'],
    'focus_question_id' => $ordinaryQuestionId, 'practice_type' => 'retry', 'count' => 5,
], [], $cookies);
mistakeHttpOk($badCsrfPractice, 'Reject a forged Mistake Notebook practice form', 303);
mistakeAssert(($badCsrfPractice['location'] ?? '') === '/mistake-notebook'
    && mistakeAttemptsHash($fixture) === $attemptsBeforeBadCsrf,
    'A forged Mistake Notebook form bypassed CSRF validation or changed attempts.');
if (!preg_match('/href="\/mistake-notebook\/review\?attempt_id=([^"&]+)&amp;question_id=' . preg_quote($ordinaryQuestionId, '/') . '"/', $notebook['output'], $reviewMatch)) {
    throw new RuntimeException('Notebook did not link the mistake to its owned attempt.');
}
$ordinaryAttemptId = rawurldecode($reviewMatch[1]);
$detail = $http->request('GET', '/mistake-notebook/review', ['attempt_id' => $ordinaryAttemptId, 'question_id' => $ordinaryQuestionId], [], [], $cookies);
$performanceMs['question_detail'] = round($detail['duration'] * 1000, 2);
mistakeHttpOk($detail, 'Open question mistake review');
mistakeAssert(str_contains($detail['output'], 'Your recorded answer')
    && str_contains($detail['output'], 'Saved explanation')
    && str_contains($detail['output'], htmlspecialchars((string) $canonicalOrdinary['explanation'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')),
    'Mistake review did not show the recorded answer and saved canonical explanation.');

$retryStart = $http->request('POST', '/mistake-notebook/practice', [], [
    'csrf_token' => mistakeCsrf($notebook['output']), 'board' => 'let', 'subject' => $target['subject'],
    'topic' => $target['topic'], 'focus_question_id' => $ordinaryQuestionId, 'practice_type' => 'retry', 'count' => 5,
], [], $cookies);
$performanceMs['retry_pool_selection_and_quiz_start'] = round($retryStart['duration'] * 1000, 2);
mistakeHttpOk($retryStart, 'Start mistake retry');
if (!preg_match('/name="question_id"\s+value="([^"]+)"/', $retryStart['output'], $retryQuestionMatch)) {
    throw new RuntimeException('Retry did not enter the ordinary quiz journey.');
}
mistakeAssert($retryQuestionMatch[1] === $ordinaryQuestionId, 'Retry did not select the eligible missed canonical question.');
$retryResult = completeSingleQuestion($http, $cookies, $ordinaryQuestionId, mistakeCsrf($retryStart['output']), true);
$performanceMs['recovery_result_summary'] = round($retryResult['duration'] * 1000, 2);
mistakeAssert(str_contains($retryResult['output'], 'Mistake retry complete')
    && str_contains($retryResult['output'], 'Correct:</strong> 1')
    && str_contains($retryResult['output'], 'Eligible recorded mistakes still available to retry'),
    'Retry results did not show the ordinary scorer result and recovery summary.');
$afterRetry = $http->request('GET', '/mistake-notebook', ['board' => 'let', 'subject' => $target['subject']], [], [], $cookies);
mistakeHttpOk($afterRetry, 'Reopen notebook after correct retry');
mistakeAssert(str_contains($afterRetry['output'], 'Correct on latest retry'), 'A correct retry erased the original mistake instead of updating its recovery status.');

$currentAttempts = $questionRepo === null ? [] : \App\Core\App::container()->get(\App\Repositories\AttemptRepository::class)->byUser($learnerId);
$practicePool = new \App\Services\Learning\MistakePracticePoolService();
$relatedStartedAt = hrtime(true);
$relatedPool = $practicePool->related($currentAttempts, 'let', $target['subject'], null, $ordinaryQuestionId, '', 20);
$performanceMs['related_pool_selection'] = round((hrtime(true) - $relatedStartedAt) / 1_000_000, 2);
$relatedIds = array_map(static fn(array $question): string => (string) ($question['id'] ?? ''), $relatedPool['questions']);
mistakeAssert($relatedIds !== [] && !in_array($ordinaryQuestionId, $relatedIds, true), 'Related-topic practice did not produce a distinct eligible question.');
foreach ($relatedPool['questions'] as $relatedQuestion) {
    mistakeAssert((string) ($relatedQuestion['taxonomy']['topic_id'] ?? '') === $target['topic'], 'Related practice used generic overlap instead of the exact taxonomy topic.');
}
$unclassifiedQuestion = $canonicalOrdinary;
$unclassifiedTaxonomy = is_array($unclassifiedQuestion['taxonomy'] ?? null) ? $unclassifiedQuestion['taxonomy'] : [];
unset($unclassifiedTaxonomy['topic_id'], $unclassifiedTaxonomy['concept_id'], $unclassifiedTaxonomy['concept_ids']);
$unclassifiedQuestion['taxonomy'] = $unclassifiedTaxonomy;
$noRelated = $practicePool->related([[
    'id' => 'attempt-batch712-no-related-' . bin2hex(random_bytes(4)), 'board' => 'let', 'subject' => $target['subject'],
    'review' => [[
        'question' => $unclassifiedQuestion, 'answered' => true, 'correct' => false,
        'userAnswer' => wrongChoice($canonicalOrdinary), 'correctAnswer' => $canonicalOrdinary['answer'],
    ]],
]], 'let', $target['subject'], null, $ordinaryQuestionId);
mistakeAssert($noRelated['questions'] === [], 'Missing topic/concept metadata was treated as proof for related practice.');
$relatedStart = $http->request('POST', '/mistake-notebook/practice', [], [
    'csrf_token' => mistakeCsrf($afterRetry['output']), 'board' => 'let', 'subject' => $target['subject'],
    'focus_question_id' => $ordinaryQuestionId, 'practice_type' => 'related', 'count' => 5,
], [], $cookies);
mistakeHttpOk($relatedStart, 'Start related-topic practice');
if (!preg_match('/name="question_id"\s+value="([^"]+)"/', $relatedStart['output'], $relatedQuestionMatch)) {
    throw new RuntimeException('Related practice did not start through the quiz.');
}
mistakeAssert($relatedQuestionMatch[1] !== $ordinaryQuestionId && in_array($relatedQuestionMatch[1], $relatedIds, true), 'Related practice repeated the missed question or left its exact topic pool.');
$relatedQuestionId = $relatedQuestionMatch[1];
$relatedResult = completeSingleQuestion($http, $cookies, $relatedQuestionId, mistakeCsrf($relatedStart['output']), true);
mistakeAssert(str_contains($relatedResult['output'], 'Related-topic practice complete'), 'Related-topic quiz did not return a recovery summary.');

// Notes-based practice creates a durable, bounded note snapshot on the ordinary attempt record.
$noteTitle = 'Batch 712 note & review';
$noteBody = '## ' . \App\Services\Shared\TaxonomyStorageService::label('topics', $target['topic'])
    . "\n\n" . (string) $canonicalOrdinary['question'] . "\n\nReview this point: <script>privateNoteText()</script>";
$notesPage = $http->request('GET', '/my-notes', [], [], [], $cookies);
mistakeHttpOk($notesPage, 'Open saved notes for note-linked mistake');
$noteSave = $http->request('POST', '/my-notes/save', [], [
    'csrf_token' => mistakeCsrf($notesPage['output']), 'title' => $noteTitle, 'board_id' => 'let', 'body' => $noteBody,
], [], $cookies);
mistakeHttpOk($noteSave, 'Save note for notes-based recovery', 303);
parse_str((string) parse_url((string) $noteSave['location'], PHP_URL_QUERY), $noteQuery);
$noteId = (string) ($noteQuery['note'] ?? '');
mistakeAssert(preg_match('/\Anote-[a-f0-9]{32}\z/', $noteId) === 1, 'Saved note did not receive an owner-scoped identity.');
$noteMatchPage = $http->request('GET', '/my-notes', ['note' => $noteId], [], [], $cookies);
mistakeHttpOk($noteMatchPage, 'Open notes practice setup');
if (!preg_match('/Questions available:\s*<\/strong>\s*(\d+)/', $noteMatchPage['output'], $matchCount)) {
    throw new RuntimeException('Notes practice did not display its eligible question count.');
}
mistakeAssert((int) $matchCount[1] > 0, 'The targeted note did not find relevant eligible canonical questions.');
$noteStart = $http->request('POST', '/my-notes/practice', [], [
    'csrf_token' => mistakeCsrf($noteMatchPage['output']), 'note_id' => $noteId, 'count' => min(5, (int) $matchCount[1]),
], [], $cookies);
mistakeHttpOk($noteStart, 'Start notes-based quiz for mistake integration');
if (!preg_match('/name="question_id"\s+value="([^"]+)"/', $noteStart['output'], $noteQuestionMatch)) {
    throw new RuntimeException('Notes-based quiz did not enter the existing quiz flow.');
}
$noteQuestionId = $noteQuestionMatch[1];
$noteQuestion = $questionRepo->find($noteQuestionId);
$noteResult = completeSingleQuestion($http, $cookies, $noteQuestionId, mistakeCsrf($noteStart['output']), false);
$allAttemptsAfterNote = json_decode((string) file_get_contents($fixture . '/attempts.json'), true, 512, JSON_THROW_ON_ERROR);
$noteAttempt = null;
foreach (array_reverse($allAttemptsAfterNote) as $attempt) {
    if (is_array($attempt) && ($attempt['notes_practice']['note_id'] ?? '') === $noteId) { $noteAttempt = $attempt; break; }
}
mistakeAssert(str_contains($noteResult['output'], 'Practice from your notes')
    && str_contains($noteResult['output'], htmlspecialchars($noteTitle, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')),
    'Notes-based result did not retain its context; latest attempt=' . json_encode(array_slice((array) end($allAttemptsAfterNote), 0, 14))
    . '; result=' . substr(preg_replace('/\s+/', ' ', strip_tags($noteResult['output'])) ?? '', 0, 500));
mistakeAssert(is_array($noteAttempt), 'Notes-based attempt did not preserve the bounded note snapshot.');
mistakeAssert(isset($noteAttempt['notes_practice']['question_notes'][$noteQuestionId]['excerpt']), 'Attempt note snapshot did not retain the excerpt for the selected question.');
$noteReview = $http->request('GET', '/mistake-notebook/review', [
    'attempt_id' => $noteAttempt['id'], 'question_id' => $noteQuestionId,
], [], [], $cookies);
mistakeHttpOk($noteReview, 'Open notes-linked mistake review');
mistakeAssert(str_contains($noteReview['output'], 'Personal note context')
    && str_contains($noteReview['output'], 'Open your current saved note')
    && str_contains($noteReview['output'], 'learner-written'), 'Mistake review did not connect its owned note snapshot.');
mistakeAssert(!str_contains($noteReview['output'], '<script>privateNoteText()</script>'), 'Learner note snapshot rendered as executable HTML.');
$noteExcerpt = (string) $noteAttempt['notes_practice']['question_notes'][$noteQuestionId]['excerpt'];
mistakeAssert($noteExcerpt === '' || str_contains($noteReview['output'], htmlspecialchars($noteExcerpt, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')), 'Escaped historical note excerpt was not displayed.');
$editedNoteTitle = $noteTitle . ' edited later';
if (!preg_match('/name="note_revision"\s+value="(\d+)"/', $noteMatchPage['output'], $noteRevisionMatch)) {
    throw new RuntimeException('The saved note form did not provide its concurrency revision.');
}
$noteEdit = $http->request('POST', '/my-notes/save', [], [
    'csrf_token' => mistakeCsrf($noteMatchPage['output']), 'note_id' => $noteId,
    'note_revision' => (int) $noteRevisionMatch[1],
    'title' => $editedNoteTitle, 'board_id' => 'let', 'body' => $noteBody . "\n\nPrivate edit after this quiz.",
], [], $cookies);
mistakeHttpOk($noteEdit, 'Edit a private note after its mistake snapshot was recorded', 303);
$noteEditPage = $http->request('GET', '/my-notes', ['note' => $noteId], [], [], $cookies);
mistakeHttpOk($noteEditPage, 'Open the edited private note');
mistakeAssert(str_contains($noteEditPage['output'], htmlspecialchars($editedNoteTitle, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')),
    'The learner current edited note was not available to its owner.');
$noteReviewAfterEdit = $http->request('GET', '/mistake-notebook/review', [
    'attempt_id' => $noteAttempt['id'], 'question_id' => $noteQuestionId,
], [], [], $cookies);
mistakeHttpOk($noteReviewAfterEdit, 'Reopen the original note-linked mistake after note editing');
mistakeAssert(str_contains($noteReviewAfterEdit['output'], htmlspecialchars($noteTitle, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'))
    && !str_contains($noteReviewAfterEdit['output'], htmlspecialchars($editedNoteTitle, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'))
    && ($noteExcerpt === '' || str_contains($noteReviewAfterEdit['output'], htmlspecialchars($noteExcerpt, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'))),
    'Editing a private note rewrote the historical attempt snapshot.');
$noteDelete = $http->request('POST', '/my-notes/delete', [], [
    'csrf_token' => mistakeCsrf($noteEditPage['output']), 'note_id' => $noteId, 'confirm_delete' => 'yes',
], [], $cookies);
mistakeHttpOk($noteDelete, 'Delete saved note after quiz', 303);
$noteReviewAfterDelete = $http->request('GET', '/mistake-notebook/review', [
    'attempt_id' => $noteAttempt['id'], 'question_id' => $noteQuestionId,
], [], [], $cookies);
mistakeHttpOk($noteReviewAfterDelete, 'Reopen note-linked mistake after note deletion');
mistakeAssert(str_contains($noteReviewAfterDelete['output'], 'Personal note context')
    && str_contains($noteReviewAfterDelete['output'], 'deleted private note text was not restored')
    && !str_contains($noteReviewAfterDelete['output'], 'Open your current saved note'), 'Deleted note was resurrected or its attempt snapshot was lost.');
$historicalNoteResult = $http->request('GET', '/history/result', ['id' => $noteAttempt['id']], [], [], $cookies);
mistakeHttpOk($historicalNoteResult, 'Reopen a notes-based historical result after note deletion');
mistakeAssert(str_contains($historicalNoteResult['output'], 'This note is no longer saved')
    && !str_contains($historicalNoteResult['output'], '/my-notes?note=' . $noteId), 'Historical result linked to a deleted note instead of retaining only its bounded snapshot.');

// GETs are read-only, foreign attempt/note IDs fail safely, and separate anonymous identities remain isolated.
$beforeGetAttempts = hash_file('sha256', $fixture . '/attempts.json');
$forged = $http->request('GET', '/mistake-notebook/review', ['attempt_id' => $noteAttempt['id'], 'question_id' => $noteQuestionId], [], [], ['PHPSESSID' => 'batch712-foreign-' . bin2hex(random_bytes(8))]);
mistakeHttpOk($forged, 'Reject another learner attempt', 303);
mistakeAssert(($forged['location'] ?? '') === '/mistake-notebook', 'Foreign attempt lookup did not fail to the safe notebook page.');
$forgedLearnerClaim = $http->request('GET', '/mistake-notebook/review', [
    'attempt_id' => $noteAttempt['id'], 'question_id' => $noteQuestionId, 'learner_id' => $learnerId,
], [], [], ['PHPSESSID' => 'batch712-forged-claim-' . bin2hex(random_bytes(8))]);
mistakeHttpOk($forgedLearnerClaim, 'Ignore a forged learner identifier on mistake review', 303);
mistakeAssert(($forgedLearnerClaim['location'] ?? '') === '/mistake-notebook',
    'A learner-supplied identity claim exposed another anonymous learner\'s attempt.');
$foreignNotebook = $http->request('GET', '/mistake-notebook', [], [], [], ['PHPSESSID' => 'batch712-empty-' . bin2hex(random_bytes(8))]);
mistakeHttpOk($foreignNotebook, 'Open an empty learner notebook');
$escapedPrivateNoteTitle = htmlspecialchars($noteTitle, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
mistakeAssert(str_contains($foreignNotebook['output'], 'There are no recorded mistakes yet')
    && !str_contains($foreignNotebook['output'], $escapedPrivateNoteTitle), 'Another anonymous learner saw private mistake or note history.');
$afterGetAttempts = hash_file('sha256', $fixture . '/attempts.json');
mistakeAssert($beforeGetAttempts === $afterGetAttempts, 'Notebook GET or foreign lookup mutated attempt history.');

$correctOnlyCookies = ['PHPSESSID' => 'batch712-correct-only-' . bin2hex(random_bytes(8))];
$correctOnlySetup = $http->request('GET', '/quiz', [
    'action' => 'start', 'exam' => 'let', 'subject' => $target['subject'], 'topic' => $target['topic'],
    'count' => 1, 'mode' => 'practice', 'difficulty' => 'mixed',
], [], [], $correctOnlyCookies);
mistakeHttpOk($correctOnlySetup, 'Open a correct-only learner quiz');
$correctOnlyStart = $http->request('POST', '/quiz', [], [
    'action' => 'start', 'exam' => 'let', 'subject' => $target['subject'], 'topic' => $target['topic'],
    'count' => 1, 'mode' => 'practice', 'difficulty' => 'mixed', 'csrf_token' => mistakeCsrf($correctOnlySetup['output']),
], [], $correctOnlyCookies);
mistakeHttpOk($correctOnlyStart, 'Start a correct-only learner quiz');
preg_match('/name="question_id"\s+value="([^"]+)"/', $correctOnlyStart['output'], $correctOnlyMatch);
mistakeAssert(isset($correctOnlyMatch[1]), 'Correct-only learner quiz did not select a question.');
completeSingleQuestion($http, $correctOnlyCookies, $correctOnlyMatch[1], mistakeCsrf($correctOnlyStart['output']), true);
$correctOnlyNotebook = $http->request('GET', '/mistake-notebook', [], [], [], $correctOnlyCookies);
mistakeHttpOk($correctOnlyNotebook, 'Open notebook after an all-correct quiz');
mistakeAssert(str_contains($correctOnlyNotebook['output'], 'There are no recorded mistakes yet')
    && !str_contains($correctOnlyNotebook['output'], 'class="mistake-card card"'), 'Correct-only activity invented a learner weakness.');

$skippedFixture = [[
    'id' => 'attempt-batch712-skipped', 'board' => 'let', 'subject' => $target['subject'],
    'score' => 0, 'total' => 1, 'percentage' => 0, 'completed' => true,
    'review' => [['question' => $canonicalOrdinary, 'answered' => false, 'correct' => false, 'userAnswer' => '', 'correctAnswer' => $canonicalOrdinary['answer']]],
]];
mistakeAssert(\App\Services\Learning\MistakeNotebookService::summaries($skippedFixture) === []
    && count(\App\Services\Learning\MistakeNotebookService::skipped($skippedFixture)) === 1, 'Unanswered questions were counted as submitted mistakes.');
$timeline = [
    ['id' => 'attempt-batch712-timeline-latest-correct', 'board' => 'let', 'subject' => $target['subject'], 'score' => 1, 'total' => 1, 'percentage' => 100, 'completed_at' => gmdate(DATE_ATOM),
        'review' => [['question' => $canonicalOrdinary, 'answered' => true, 'correct' => true, 'userAnswer' => $canonicalOrdinary['answer']]]],
    ['id' => 'attempt-batch712-timeline-second-miss', 'board' => 'let', 'subject' => $target['subject'], 'score' => 0, 'total' => 1, 'percentage' => 0, 'completed_at' => gmdate(DATE_ATOM, time() - 60),
        'review' => [['question' => $canonicalOrdinary, 'answered' => true, 'correct' => false, 'userAnswer' => 'wrong-second']]],
    ['id' => 'attempt-batch712-timeline-first-miss', 'board' => 'let', 'subject' => $target['subject'], 'score' => 0, 'total' => 1, 'percentage' => 0, 'completed_at' => gmdate(DATE_ATOM, time() - 120),
        'review' => [['question' => $canonicalOrdinary, 'answered' => true, 'correct' => false, 'userAnswer' => 'wrong-first']]],
];
$timelineSummary = \App\Services\Learning\MistakeNotebookService::summaries($timeline);
mistakeAssert(count($timelineSummary) === 1 && $timelineSummary[0]['wrong_count'] === 2
    && $timelineSummary[0]['attempt_count'] === 3
    && $timelineSummary[0]['latest_status'] === 'CORRECT_ON_MOST_RECENT_RETRY',
    'Multiple misses, repeated attempts, and a later correct answer were not summarized from actual events.');
$timeline[] = ['id' => 'attempt-batch712-timeline-newest-miss', 'board' => 'let', 'subject' => $target['subject'], 'score' => 0, 'total' => 1, 'percentage' => 0, 'completed_at' => gmdate(DATE_ATOM, time() + 60),
    'review' => [['question' => $canonicalOrdinary, 'answered' => true, 'correct' => false, 'userAnswer' => 'wrong-latest']]];
$latestMissSummary = \App\Services\Learning\MistakeNotebookService::summaries($timeline);
mistakeAssert(($latestMissSummary[0]['wrong_count'] ?? 0) === 3
    && ($latestMissSummary[0]['latest_status'] ?? '') === 'STILL_NEEDS_PRACTICE',
    'A new incorrect answer did not supersede the earlier correct retry outcome.');
$otherBoardFixture = $skippedFixture[0];
$otherBoardFixture['board'] = 'nursing';
$otherBoardFixture['id'] = 'attempt-batch712-other-board';
$otherBoardFixture['review'][0]['answered'] = true;
$otherBoardFixture['review'][0]['correct'] = false;
$contextGroups = \App\Services\Learning\MistakeNotebookService::summaries([$firstAttempt, $otherBoardFixture]);
mistakeAssert(count(array_filter($contextGroups, static fn(array $item): bool => (string) $item['question_id'] === $ordinaryQuestionId)) >= 2,
    'The same canonical identity was merged across distinct exam contexts.');
// A CSE item with a historical miss remains reviewable but cannot bypass the live exclusion on retry.
$q6403 = $questionRepo->find('6403');
$cseRequest = new SelectionRequest(subject: '*', domain: null, difficultyDistribution: [], questionCount: 100, exam: 'civil-service', civilServiceLevel: 'PROFESSIONAL');
$cseEligible = QuestionPoolFilter::filter($eligibleRows, $cseRequest, $eligibility);
$ordinaryCse = null;
foreach ($cseEligible as $candidate) if ((string) ($candidate['id'] ?? '') !== '6403') { $ordinaryCse = $candidate; break; }
mistakeAssert(is_array($q6403) && is_array($ordinaryCse), 'The Q6403 and ordinary CSE selection fixtures were not available.');
$_SESSION = ['learner_identity' => $learnerId];
$attemptRepo = \App\Core\App::container()->get(\App\Repositories\AttemptRepository::class);
$cseReview = [];
foreach ([$q6403, $ordinaryCse] as $question) {
    $wrongAnswer = '';
    foreach ((array) ($question['choices'] ?? []) as $choice) {
        if (is_scalar($choice) && strcasecmp(trim((string) $choice), trim((string) ($question['answer'] ?? ''))) !== 0) { $wrongAnswer = (string) $choice; break; }
    }
    $cseReview[] = [
        'question' => $question, 'choices' => $question['choices'] ?? [], 'userAnswer' => $wrongAnswer,
        'correctAnswer' => $question['answer'] ?? '', 'correct' => false, 'answered' => true,
        'explanation' => $question['explanation'] ?? '',
    ];
}
$cseAttempt = $attemptRepo->create([
    'id' => 'attempt-batch712-cse-' . bin2hex(random_bytes(5)), 'user_id' => $learnerId,
    'board' => 'civil-service', 'subject' => '*', 'civil_service_level' => 'PROFESSIONAL',
    'mode' => 'practice', 'score' => 0, 'total' => 2, 'percentage' => 0, 'completed' => true,
    'completed_at' => gmdate(DATE_ATOM), 'review' => $cseReview,
]);
$q6403ReviewPage = $http->request('GET', '/mistake-notebook/review', [
    'attempt_id' => $cseAttempt['id'], 'question_id' => '6403',
], [], [], $cookies);
mistakeHttpOk($q6403ReviewPage, 'Open historical Q6403 review');
mistakeAssert(str_contains($q6403ReviewPage['output'], 'Historical scoring hold')
    && str_contains($q6403ReviewPage['output'], 'not an approved correction')
    && !str_contains($q6403ReviewPage['output'], 'Retry Missed Questions'), 'Q6403 history was not visibly held or exposed a retry action.');
$cseRetry = $practicePool->retry($attemptRepo->byUser($learnerId), 'civil-service', '', 'PROFESSIONAL');
$cseRetryIds = array_map(static fn(array $question): string => (string) ($question['id'] ?? ''), $cseRetry['questions']);
mistakeAssert(!in_array('6403', $cseRetryIds, true) && in_array((string) $ordinaryCse['id'], $cseRetryIds, true), 'Q6403 escaped CSE quarantine or ordinary CSE practice was unintentionally disabled.');
$cseHold = $http->request('POST', '/mistake-notebook/practice', [], [
    'csrf_token' => mistakeCsrf($afterRetry['output']), 'board' => 'civil-service', 'subject' => '*',
    'focus_question_id' => '6403', 'civil_service_level' => 'PROFESSIONAL', 'practice_type' => 'retry', 'count' => 5,
], [], $cookies);
mistakeHttpOk($cseHold, 'Keep Q6403 out of CSE retry', 303);
mistakeAssert(($cseHold['location'] ?? '') === '/mistake-notebook', 'A quarantined-only retry unexpectedly started a session.');

$canonicalHashAfter = hash_file('sha256', $questionPath);
mistakeAssert($canonicalHashBefore === $canonicalHashAfter, 'Canonical questions or answer keys changed during mistake recovery.');
$preservedAttempts = json_decode((string) file_get_contents($fixture . '/attempts.json'), true, 512, JSON_THROW_ON_ERROR);
$preservedOriginal = null;
foreach ($preservedAttempts as $attempt) if (is_array($attempt) && ($attempt['id'] ?? '') === $ordinaryAttemptId) $preservedOriginal = $attempt;
mistakeAssert(is_array($preservedOriginal)
    && hash('sha256', json_encode($preservedOriginal, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR))
        === hash('sha256', json_encode($firstAttempt, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)),
    'A retry or note update modified the original incorrect attempt record.');
echo "[PASS] Batch 712 ordinary quiz → mistake notebook → explanation → eligible retry → updated attempt status.\n";
echo "[PASS] Same-topic practice used distinct exact-topic eligible questions through the production quiz route.\n";
echo "[PASS] Notes-based attempt context survived note deletion without restoring private note storage.\n";
echo "[PASS] Skipped/foreign history and Q6403 CSE quarantine remain separately handled.\n";
echo 'ORDINARY_QUESTION_ID=' . $ordinaryQuestionId . PHP_EOL;
echo 'RELATED_QUESTION_ID=' . $relatedQuestionId . PHP_EOL;
echo 'NOTES_QUESTION_ID=' . $noteQuestionId . PHP_EOL;
echo 'FIXTURE_PERFORMANCE_MS=' . json_encode($performanceMs, JSON_UNESCAPED_SLASHES) . PHP_EOL;
echo 'CANONICAL_SHA256=' . $canonicalHashAfter . PHP_EOL;
