<?php

declare(strict_types=1);

require_once __DIR__ . '/Support/StaticStorageFixture.php';
$root = dirname(__DIR__, 2);
$fixture = StaticStorageFixture::create($root, 'batch711-notes-practice');
register_shutdown_function(static function () use ($fixture): void {
    $real = realpath($fixture);
    $tmp = realpath(sys_get_temp_dir());
    if (!is_string($real) || !is_string($tmp) || !str_starts_with($real, $tmp . DIRECTORY_SEPARATOR)
        || !str_contains(basename($real), 'boardprep-batch711-notes-practice-')) return;
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($real, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
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

function notesAssert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function notesRequestOk(array $response, string $name, int $status = 200): void
{
    if (!$response['success'] || $response['status'] !== $status) {
        throw new RuntimeException($name . ' failed: HTTP ' . $response['status'] . ', exit ' . $response['exitCode']
            . ', ' . substr(preg_replace('/\s+/', ' ', strip_tags((string) $response['output'])) ?? '', 0, 260)
            . ', ' . substr((string) $response['stderr'], -500));
    }
}

$canonicalPath = $fixture . '/questions.json';
$canonicalHashBefore = hash_file('sha256', $canonicalPath);
$canonical = json_decode((string) file_get_contents($canonicalPath), true, 512, JSON_THROW_ON_ERROR);
$eligibleQuestion = null;
foreach ($canonical as $candidate) {
    $taxonomy = is_array($candidate['taxonomy'] ?? null) ? $candidate['taxonomy'] : [];
    if (is_array($candidate)
        && strtolower((string) ($taxonomy['board_id'] ?? '')) === 'let'
        && trim((string) ($taxonomy['subject_id'] ?? '')) !== ''
        && trim((string) ($candidate['question'] ?? '')) !== ''
        && is_array($candidate['choices'] ?? null) && count($candidate['choices']) > 1
        && trim((string) ($candidate['answer'] ?? '')) !== '') {
        $eligibleQuestion = $candidate;
        break;
    }
}
notesAssert(is_array($eligibleQuestion), 'A canonical LET question fixture was not available.');
$questionId = (string) $eligibleQuestion['id'];
$taxonomy = (array) ($eligibleQuestion['taxonomy'] ?? []);
$topicId = trim((string) ($taxonomy['topic_id'] ?? ''));
$topicLabel = $topicId !== '' ? \App\Services\Shared\TaxonomyStorageService::label('topics', $topicId) : 'Reading';
$noteBody = '## ' . $topicLabel . "\n\nApply the key distinction to a short example.\n\nSa Filipino: ipaliwanag ang pagkakaiba at gamitin ang halimbawa sa pag-aaral.";

// CRUD and learner privacy use the same storage identity as the application.
$_SESSION = ['learner_identity' => 'learner-' . str_repeat('a', 32)];
$notesService = new \App\Services\Learning\LearnerNotesService();
$created = $notesService->save(['title' => 'My LET notes', 'board_id' => 'let', 'body' => $noteBody]);
notesAssert(($created['user_id'] ?? '') === $_SESSION['learner_identity'], 'Created notes do not belong to the active learner.');
notesAssert($notesService->find((string) $created['id'])['body'] === $noteBody, 'Created note did not reopen with its paragraphs.');
$editedInput = ['note_id' => $created['id'], 'note_revision' => $created['note_revision'], 'title' => 'Edited LET notes', 'board_id' => 'let', 'body' => $noteBody . "\n\nEdited."];
$edited = $notesService->save($editedInput);
notesAssert(($edited['title'] ?? '') === 'Edited LET notes' && str_ends_with((string) $edited['body'], 'Edited.')
    && ($edited['note_revision'] ?? 0) === 2, 'Note edit was not saved as the next revision.');
$lostResponseRetry = $notesService->save($editedInput);
notesAssert(($lostResponseRetry['id'] ?? null) === $edited['id']
    && ($lostResponseRetry['note_revision'] ?? 0) === 2
    && ($lostResponseRetry['body'] ?? null) === $edited['body'], 'Retrying a saved note edit did not confirm the existing write.');
try {
    $notesService->save(array_merge($editedInput, ['body' => $noteBody . "\n\nA stale edit."]));
    throw new RuntimeException('A stale note edit overwrote the newer saved revision.');
} catch (InvalidArgumentException $exception) {
    notesAssert(str_contains($exception->getMessage(), 'changed after you opened it'), 'Stale note edit returned an unclear error.');
}
notesAssert(($notesService->find((string) $created['id'])['body'] ?? null) === $edited['body'], 'Stale note edit changed the authoritative saved body.');
$_SESSION = ['learner_identity' => 'learner-' . str_repeat('b', 32)];
notesAssert($notesService->all() === [] && $notesService->find((string) $created['id']) === null, 'Another learner could read a private note.');
$_SESSION = ['learner_identity' => 'learner-' . str_repeat('a', 32)];
notesAssert($notesService->delete((string) $created['id']), 'The owner could not delete their note.');
notesAssert(!$notesService->delete((string) $created['id']), 'Deleting a stale note should fail safely.');
try {
    $notesService->save(['title' => 'Too long', 'board_id' => 'let', 'body' => str_repeat('x', 12001)]);
    throw new RuntimeException('Oversized notes were accepted.');
} catch (InvalidArgumentException) {
}

// Matching is board-scoped, explains its evidence, and excludes Q6403 via the live pool gate.
$matchStarted = microtime(true);
$match = (new \App\Services\Learning\NotesQuestionMatcherService())->match('let', $noteBody);
$matchMilliseconds = round((microtime(true) - $matchStarted) * 1000, 2);
$matchedIds = array_map(static fn(array $item): string => (string) ($item['question_id'] ?? ''), $match['candidates']);
notesAssert(in_array($questionId, $matchedIds, true), 'A clear canonical LET topic was not matched.');
notesAssert($match['question_count'] === count($match['candidates']), 'Matched question count did not reconcile.');
notesAssert(!in_array($questionId, array_map(static fn(array $item): string => (string) ($item['question_id'] ?? ''),
    (new \App\Services\Learning\NotesQuestionMatcherService())->match('nursing', $noteBody)['candidates']), true),
    'A canonical question leaked into a board where it is not eligible.');
$noMatch = (new \App\Services\Learning\NotesQuestionMatcherService())->match('let', '## Xylophonic viridion\n\nQuasiperipheral zephyric observability.');
notesAssert($noMatch['question_count'] === 0, 'Unrelated keywords were given a personalized match.');
$q6403 = (new \App\Repositories\QuestionRepository(\App\Core\App::storage()))->find('6403');
if (is_array($q6403)) {
    $q6403Match = (new \App\Services\Learning\NotesQuestionMatcherService())->match('civil-service', (string) ($q6403['question'] ?? ''));
    notesAssert(!in_array('6403', array_map(static fn(array $item): string => (string) ($item['question_id'] ?? ''), $q6403Match['candidates']), true), 'Q6403 escaped the existing CSE selection exclusion.');
}

// Exercise the production HTTP routes with isolated storage and learner sessions.
require_once dirname(__DIR__) . '/Doctor/Project/BoardPrep/Simulation/HttpSimulator.php';
$http = new \Tools\Doctor\Project\BoardPrep\Simulation\HttpSimulator($root . '/public/index.php');
$cookies = ['PHPSESSID' => 'batch711-notes-' . bin2hex(random_bytes(6))];
$page = $http->request('GET', '/my-notes', [], [], [], $cookies);
notesRequestOk($page, 'My Notes page');
notesAssert(str_contains($page['output'], 'Practice Based on My Notes') && str_contains($page['output'], 'name="body"'), 'My Notes form is missing its learner-facing controls.');
if (!preg_match('/name="csrf_token" value="([a-f0-9]{64})"/', $page['output'], $csrfMatch)) throw new RuntimeException('My Notes page did not provide a CSRF token.');
if (!preg_match('/name="create_request_id" value="([a-f0-9]{32})"/', $page['output'], $createRequestMatch)) throw new RuntimeException('New note form did not provide a retry-safe request identity.');
$csrf = $csrfMatch[1];
$createRequestId = $createRequestMatch[1];
$csrfRejected = $http->request('POST', '/my-notes/save', [], [
    'title' => 'Should not save', 'board_id' => 'let', 'body' => 'unauthorized note text',
], [], $cookies);
notesRequestOk($csrfRejected, 'Reject note write without CSRF', 303);
$unsafeNote = '<script>window.notePayloadExecuted=true</script>';
$createNotePayload = [
    'csrf_token' => $csrf,
    'create_request_id' => $createRequestId,
    'title' => 'HTTP notes & <script>',
    'board_id' => 'let',
    'body' => $noteBody . "\n\n" . $unsafeNote,
];
$save = $http->request('POST', '/my-notes/save', [], $createNotePayload, [], $cookies);
notesRequestOk($save, 'Create note through HTTP', 303);
parse_str((string) parse_url((string) $save['location'], PHP_URL_QUERY), $savedQuery);
$savedNoteId = (string) ($savedQuery['note'] ?? '');
notesAssert(preg_match('/\Anote-[a-f0-9]{32}\z/', $savedNoteId) === 1, 'Saved note redirect did not identify the created note.');
$savedCreateRetry = $http->request('POST', '/my-notes/save', [], $createNotePayload, [], $cookies);
notesRequestOk($savedCreateRetry, 'Retry saved note creation after a lost response', 303);
notesAssert($savedCreateRetry['location'] === $save['location'], 'Retrying a saved note creation created a second note identity.');
$savedCreateConflict = $http->request('POST', '/my-notes/save', [], array_merge($createNotePayload, [
    'title' => 'Conflicting replay',
]), [], $cookies);
notesRequestOk($savedCreateConflict, 'Reject conflicting reuse of note creation request', 200);
notesAssert(str_contains($savedCreateConflict['output'], 'already used with different content'), 'Conflicting create retry did not explain that the original note was kept.');
$detail = $http->request('GET', '/my-notes', ['note' => $savedNoteId], [], [], $cookies);
notesRequestOk($detail, 'Reopen saved note');
if (!preg_match('/name="note_revision" value="(\d+)"/', $detail['output'], $revisionMatch)) {
    throw new RuntimeException('Saved note editor did not carry its concurrency revision.');
}
$savedNoteRevision = (int) $revisionMatch[1];
notesAssert(str_contains($detail['output'], '&lt;script&gt;window.notePayloadExecuted=true&lt;/script&gt;'), 'User-supplied script text was not safely escaped in the note editor.');
notesAssert(!str_contains($detail['output'], '<script>window.notePayloadExecuted=true</script>'), 'User-supplied note content rendered executable markup.');
notesAssert(!str_contains($detail['output'], 'Should not save'), 'A note write without a valid CSRF token was persisted.');
notesAssert(str_contains($detail['output'], 'Questions available:'), 'HTTP note page did not calculate a topic match.');
notesAssert(substr_count((string) file_get_contents($fixture . '/learner_notes.json'), $createRequestId) === 1, 'Retrying note creation duplicated or rewrote the persisted request identity.');
$available = 0;
if (preg_match('/Questions available:\s*<\/strong>\s*(\d+)/', $detail['output'], $availableMatch)) $available = (int) $availableMatch[1];
notesAssert($available > 0, 'HTTP note matcher found no sufficiently relevant questions for a clear topic.');
$sessionCount = $available >= 5 ? 5 : $available;
$start = $http->request('POST', '/my-notes/practice', [], [
    'csrf_token' => $csrf, 'note_id' => $savedNoteId, 'count' => $sessionCount,
], [], $cookies);
notesRequestOk($start, 'Start notes-based practice');
if (!preg_match('/name="question_id"\s+value="([^"]+)"/', $start['output'], $questionMatch)
    || !preg_match('/name="csrf_token"\s+value="([a-f0-9]{64})"/', $start['output'], $quizCsrfMatch)) {
    throw new RuntimeException('Notes-based practice did not enter the existing quiz flow.');
}
$currentQuestionId = $questionMatch[1];
$currentQuestion = null;
foreach ($canonical as $candidate) if ((string) ($candidate['id'] ?? '') === $currentQuestionId) { $currentQuestion = $candidate; break; }
notesAssert(is_array($currentQuestion), 'Practice selected a question absent from the canonical bank.');
$correctIndex = null;
foreach (array_values((array) $currentQuestion['choices']) as $index => $choice) {
    if (strcasecmp(trim((string) $choice), trim((string) $currentQuestion['answer'])) === 0) { $correctIndex = $index; break; }
}
notesAssert(is_int($correctIndex), 'The selected canonical answer did not map to a stored choice.');
$answerIndex = count((array) $currentQuestion['choices']) > 1 ? ($correctIndex + 1) % count($currentQuestion['choices']) : $correctIndex;
$answerCode = chr(65 + $answerIndex);
$submit = $http->request('POST', '/quiz', [], [
    'action' => 'submit', 'question_id' => $currentQuestionId, 'answer' => $answerCode, 'csrf_token' => $quizCsrfMatch[1],
], [], $cookies);
notesRequestOk($submit, 'Submit a notes practice answer');
notesAssert(str_contains($submit['output'], 'Incorrect'), 'The existing quiz scorer did not report the intentionally incorrect answer.');
$finish = $http->request('POST', '/quiz', [], ['action' => 'finish', 'csrf_token' => $quizCsrfMatch[1]], [], $cookies);
notesRequestOk($finish, 'Finish notes practice', 303);
$result = $http->request('GET', '/quiz', ['action' => 'result'], [], [], $cookies);
notesRequestOk($result, 'Review notes practice result');
notesAssert(str_contains($result['output'], 'Practice from your notes')
    && str_contains($result['output'], 'Topics practiced')
    && str_contains($result['output'], 'Topics to revisit')
    && str_contains($result['output'], 'From your note')
    && str_contains($result['output'], 'learner-written material'), 'Result review did not connect the existing score to the selected note.');
$history = $http->request('GET', '/history', [], [], [], $cookies);
$progress = $http->request('GET', '/progress', [], [], [], $cookies);
notesRequestOk($history, 'Notes practice history');
notesRequestOk($progress, 'Notes practice progress');

// A note with no defensible topic match must not open an unrelated quiz.
$noMatchSave = $http->request('POST', '/my-notes/save', [], [
    'csrf_token' => $csrf, 'title' => 'Unmatched notes', 'board_id' => 'let',
    'body' => "## Xylophonic viridion\n\nQuasiperipheral zephyric observability.",
], [], $cookies);
notesRequestOk($noMatchSave, 'Save note with no matching questions', 303);
parse_str((string) parse_url((string) $noMatchSave['location'], PHP_URL_QUERY), $noMatchQuery);
$noMatchNoteId = (string) ($noMatchQuery['note'] ?? '');
notesAssert(preg_match('/\Anote-[a-f0-9]{32}\z/', $noMatchNoteId) === 1, 'No-match note did not save normally.');
$noMatchStart = $http->request('POST', '/my-notes/practice', [], [
    'csrf_token' => $csrf, 'note_id' => $noMatchNoteId, 'count' => 5,
], [], $cookies);
notesRequestOk($noMatchStart, 'Refuse unrelated no-match practice', 303);
notesAssert(str_contains((string) $noMatchStart['location'], 'note=' . $noMatchNoteId), 'No-match recovery did not return to the selected note.');
$noMatchDelete = $http->request('POST', '/my-notes/delete', [], [
    'csrf_token' => $csrf, 'note_id' => $noMatchNoteId, 'confirm_delete' => 'yes',
], [], $cookies);
notesRequestOk($noMatchDelete, 'Delete no-match note', 303);

// Editing after a completed quiz does not rewrite its private note snapshot.
$postQuizEdit = $http->request('POST', '/my-notes/save', [], [
    'csrf_token' => $csrf, 'note_id' => $savedNoteId, 'note_revision' => $savedNoteRevision, 'title' => 'Edited after quiz',
    'board_id' => 'let', 'body' => $noteBody . "\n\nEdited after completion.",
], [], $cookies);
notesRequestOk($postQuizEdit, 'Edit note after quiz completion', 303);
$resultAfterEdit = $http->request('GET', '/quiz', ['action' => 'result'], [], [], $cookies);
notesRequestOk($resultAfterEdit, 'Retain result after note edit');
notesAssert(str_contains($resultAfterEdit['output'], 'HTTP notes &amp; &lt;script&gt;'), 'Editing a note rewrote the completed quiz note snapshot.');
$otherLearner = $http->request('GET', '/my-notes', [], [], [], ['PHPSESSID' => 'batch711-other-' . bin2hex(random_bytes(6))]);
notesRequestOk($otherLearner, 'Separate learner notes page');
notesAssert(!str_contains($otherLearner['output'], 'HTTP notes &amp; &lt;script&gt;'), 'Another learner could see a private note title.');

$delete = $http->request('POST', '/my-notes/delete', [], [
    'csrf_token' => $csrf, 'note_id' => $savedNoteId, 'confirm_delete' => 'yes',
], [], $cookies);
notesRequestOk($delete, 'Delete note through HTTP', 303);
$afterDelete = $http->request('GET', '/my-notes', [], [], [], $cookies);
notesRequestOk($afterDelete, 'Notes page after deletion');
notesAssert(!str_contains($afterDelete['output'], 'HTTP notes &amp; &lt;script&gt;'), 'Deleted note remained in its owner list.');

// The existing anonymous learner export/clear contract now includes private notes.
$_SESSION = ['learner_identity' => 'learner-' . str_repeat('c', 32)];
$privacyNote = (new \App\Services\Learning\LearnerNotesService())->save([
    'title' => 'Privacy export note', 'board_id' => 'let', 'body' => 'Private body for the current learner only.',
]);
\App\Core\App::storage()->create('learner-question-reports', [
    'id' => 'qir-' . str_repeat('c', 32),
    'user_id' => $_SESSION['learner_identity'],
    'question_id' => $questionId,
    'board_id' => 'let',
    'subject_id' => (string) ($taxonomy['subject_id'] ?? ''),
    'type' => 'SOURCE_CONCERN',
    'description' => 'Private learner concern fixture.',
    'status' => 'SUBMITTED',
]);
$anonymousData = \App\Services\Privacy\AnonymousLearnerDataService::current();
$anonymousExport = $anonymousData->export();
notesAssert(count($anonymousExport['records']['learner_notes'] ?? []) === 1
    && ($anonymousExport['records']['learner_notes'][0]['body'] ?? '') === 'Private body for the current learner only.'
    && count($anonymousExport['records']['question_issue_reports'] ?? []) === 1
    && ($anonymousExport['records']['question_issue_reports'][0]['question_id'] ?? '') === $questionId,
    'Anonymous learner export omitted the owner’s private note or question report.');
$backupPath = $fixture . '/batch711-notes-backup';
$backupReport = \App\Services\Operations\BackupService::create($root, $backupPath);
notesAssert(($backupReport['status'] ?? '') === 'PASS'
    && is_file($backupPath . '/json/learner_notes.json')
    && is_file($backupPath . '/json/learner-question-reports.json'), 'The existing mutable-data backup omitted saved learner notes or question reports.');
$restorePath = $fixture . '/batch711-notes-restore';
$restoreReport = \App\Services\Operations\BackupService::restore($root, $backupPath, $restorePath);
$restoredNotes = json_decode((string) file_get_contents($restorePath . '/learner_notes.json'), true, 512, JSON_THROW_ON_ERROR);
$restoredReports = json_decode((string) file_get_contents($restorePath . '/learner-question-reports.json'), true, 512, JSON_THROW_ON_ERROR);
notesAssert(($restoreReport['status'] ?? '') === 'PASS'
    && count($restoredNotes) === 1
    && ($restoredNotes[0]['body'] ?? '') === 'Private body for the current learner only.'
    && count($restoredReports) === 1
    && ($restoredReports[0]['question_id'] ?? '') === $questionId,
    'The existing mutable-data restore did not preserve learner notes and question reports.');
$cleared = $anonymousData->clear();
notesAssert(($cleared['learner_notes'] ?? 0) === 1
    && ($cleared['question_issue_reports'] ?? 0) === 1
    && $anonymousData->summary()['learner_notes'] === 0
    && $anonymousData->summary()['question_issue_reports'] === 0,
    'Anonymous learner clear did not remove only the current learner note and question report.');

// Account restore accepts legacy archives, the prior notes-bearing archive,
// and the current archive shape that also carries learner issue reports.
$accountId = 'account-' . str_repeat('d', 32);
$accountLearnerId = 'account-learner-' . str_repeat('d', 32);
$account = [
    'account_id' => $accountId, 'learner_id' => $accountLearnerId, 'username' => 'batch711-fixture',
    'password_hash' => password_hash('Batch711 fixture password', PASSWORD_DEFAULT),
    'created_at' => gmdate(DATE_ATOM), 'updated_at' => gmdate(DATE_ATOM), 'auth_version' => 1,
    'status' => 'active', 'preferences' => [], 'recovery_code_hashes' => [],
];
$accountCollections = [
    'quiz_attempts' => [], 'weakness_summaries' => [], 'flashcard_reviews' => [],
    'learner_notes' => [[
        'id' => 'note-' . str_repeat('e', 32), 'user_id' => $accountLearnerId,
        'title' => 'Private account note', 'body' => 'Account-owned note body.', 'board_id' => 'let',
    ]],
    'question_issue_reports' => [[
        'id' => 'qir-' . str_repeat('f', 32), 'user_id' => $accountLearnerId,
        'question_id' => 'synthetic-account-question', 'type' => 'SOURCE_CONCERN',
        'description' => 'Account-owned concern.', 'status' => 'SUBMITTED',
    ]],
];
$makeRestorePayload = static function (array $collections) use ($account): array {
    $names = array_keys($collections);
    $counts = array_map('count', $collections);
    $counts['credential_profile'] = 1;
    $included = [...$names, 'credential_profile'];
    return [
        'payload_schema_version' => 1,
        'manifest' => [
            'format' => 'boardprep-account-backup-payload-v1', 'created_at' => gmdate(DATE_ATOM),
            'included_collections' => $included, 'record_counts' => $counts,
            'collection_schema_versions' => array_fill_keys($included, 1),
        ],
        'account' => $account, 'collections' => $collections,
    ];
};
\App\Services\Accounts\AccountRestoreService::validatePayload($makeRestorePayload($accountCollections));
$previousCurrentCollections = $accountCollections;
unset($previousCurrentCollections['question_issue_reports']);
\App\Services\Accounts\AccountRestoreService::validatePayload($makeRestorePayload($previousCurrentCollections));
$legacyCollections = $previousCurrentCollections;
unset($legacyCollections['learner_notes']);
\App\Services\Accounts\AccountRestoreService::validatePayload($makeRestorePayload($legacyCollections));

$canonicalHashAfter = hash_file('sha256', $canonicalPath);
notesAssert($canonicalHashBefore === $canonicalHashAfter, 'Canonical questions or answer keys changed during notes practice.');
echo "[PASS] Batch 711 notes CRUD, ownership, conservative board matching, no-match handling, Q6403 containment, escaped input, production quiz scoring/results/history/progress, and isolated storage.\n";
echo 'MATCHER_MS=' . $matchMilliseconds . "\n";
echo 'CANONICAL_SHA256=' . $canonicalHashAfter . "\n";
