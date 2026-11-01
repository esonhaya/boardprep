<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

use App\Services\Accounts\AccountRepository;
use App\Services\Accounts\AccountRestoreService;
use App\Services\Accounts\AccountService;
use App\Services\Question\AcademicReviewerAuthorizationService;
use App\Services\Question\QuestionIssueReportService;
use App\Storage\JsonStorage;
use App\Storage\SqliteStorage;

function issueWorkflowAssert(bool $ok, string $message): void
{
    if (!$ok) throw new RuntimeException($message);
}

$root = sys_get_temp_dir() . '/boardprep-question-report-workflow-' . bin2hex(random_bytes(8));
$appStorage = $root . '/app-storage';
mkdir($appStorage, 0700, true);
foreach ([
    'APP_ENV' => 'testing',
    'APP_DEBUG' => 'false',
    'BOARDPREP_ACCOUNTS_ENABLED' => 'true',
    'DB_DRIVER' => 'json',
    'APP_STORAGE_PATH' => $appStorage,
] as $key => $value) {
    putenv($key . '=' . $value);
    $_ENV[$key] = $value;
}
\App\Core\App::boot();

$exercise = static function (\App\Contracts\StorageInterface $storage, string $driver): void {
    $learner = 'learner-' . str_repeat($driver === 'json' ? 'a' : 'c', 32);
    $otherLearner = 'learner-' . str_repeat($driver === 'json' ? 'b' : 'd', 32);
    $attemptId = 'attempt-' . $driver . '-owned';
    $question = [
        'id' => 'q-' . $driver . '-archived',
        'question' => 'Synthetic archived question snapshot.',
        'answer' => 'Protected answer never shown in the form.',
        'explanation' => 'Protected explanation never shown in the form.',
        'taxonomy' => ['board_id' => 'let', 'subject_id' => 'english'],
    ];
    $storage->create('attempts', [
        'id' => $attemptId,
        'user_id' => $learner,
        'completed' => true,
        'board' => 'let',
        'subject' => 'english',
        'review' => [['question' => $question, 'answered' => false, 'correct' => false]],
    ]);
    $service = new QuestionIssueReportService($storage);
    $context = $service->questionContextForAttempt($learner, $attemptId, $question['id']);
    issueWorkflowAssert(is_array($context) && ($context['archived'] ?? false) === true
        && ($context['question'] ?? null) === $question['question']
        && !array_key_exists('answer', $context), $driver . ' report context did not use a safe archived attempt snapshot.');
    issueWorkflowAssert($service->questionContextForAttempt($otherLearner, $attemptId, $question['id']) === null,
        $driver . ' report context exposed a foreign learner attempt.');
    issueWorkflowAssert($service->questionContextForAttempt($learner, $attemptId, 'not-in-result') === null,
        $driver . ' report context accepted a question outside the owned result.');

    $submitted = $service->submit($learner, $attemptId, $question['id'], 'SOURCE_CONCERN', 'Synthetic note.');
    issueWorkflowAssert(($submitted['duplicate'] ?? true) === false, $driver . ' valid archived-question report was not saved.');
    $reportId = (string) ($submitted['report']['id'] ?? '');
    $records = $storage->all(QuestionIssueReportService::COLLECTION);
    issueWorkflowAssert(count($records) === 1
        && ($records[0]['attempt_id'] ?? null) === $attemptId
        && ($records[0]['question_version_sha256'] ?? null) === hash('sha256', json_encode($question, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR))
        && count((array) ($records[0]['status_history'] ?? [])) === 1,
        $driver . ' report storage omitted owned-attempt provenance or the initial status event.');
    issueWorkflowAssert(!array_key_exists('attempt_id', $submitted['report']),
        $driver . ' learner report view exposed its private result reference.');
    $duplicate = $service->submit($learner, $attemptId, $question['id'], 'SOURCE_CONCERN', 'Retry.');
    issueWorkflowAssert(($duplicate['duplicate'] ?? false) === true && count($storage->all(QuestionIssueReportService::COLLECTION)) === 1,
        $driver . ' retry created a duplicate report.');

    $reviewer = 'account-' . str_repeat($driver === 'json' ? 'e' : 'f', 32);
    $grant = [
        'id' => 'grant-' . $driver,
        'account_id' => $reviewer,
        'authorization_status' => 'ACTIVE',
        'qualification_type' => 'SUBJECT_EXPERT',
        'qualification_reference' => 'synthetic-reviewer-fixture',
        'authorized_by' => 'synthetic-owner-fixture',
        'authorized_at' => gmdate(DATE_ATOM),
        'expires_at' => '2099-12-31T23:59:59+00:00',
        'scopes' => [['board_id' => 'let', 'subject_id' => 'english', 'capabilities' => ['REVIEW']]],
    ];
    $authorization = new AcademicReviewerAuthorizationService($storage, [$grant]);
    $scopes = $authorization->scopesForAccount($reviewer, 'REVIEW');
    issueWorkflowAssert($service->transition($reportId, 'UNDER_REVIEW', $reviewer, $authorization),
        $driver . ' scoped reviewer could not start review.');
    issueWorkflowAssert($service->transition($reportId, 'UNDER_REVIEW', $reviewer, $authorization)
        && count((array) ($storage->find(QuestionIssueReportService::COLLECTION, $reportId)['status_history'] ?? [])) === 2,
        $driver . ' repeated reviewer request was not idempotent.');
    issueWorkflowAssert($service->transition($reportId, 'RESOLVED', $reviewer, $authorization),
        $driver . ' scoped reviewer could not resolve the report.');
    $resolved = $service->reviewerPage($scopes, 'RESOLVED', 1);
    $item = $resolved['items'][0] ?? [];
    $history = is_array($item['status_history'] ?? null) ? $item['status_history'] : [];
    issueWorkflowAssert(($item['status'] ?? '') === 'RESOLVED'
        && ($item['attempt_id'] ?? '') === $attemptId
        && count($history) === 3
        && ($history[0]['to'] ?? '') === 'SUBMITTED'
        && ($history[1]['to'] ?? '') === 'UNDER_REVIEW'
        && ($history[2]['to'] ?? '') === 'RESOLVED',
        $driver . ' reviewer queue did not preserve an ordered status transition history.');
    foreach ($history as $event) {
        issueWorkflowAssert(!array_key_exists('reviewer_account_id', $event) && !array_key_exists('reviewer_username', $event),
            $driver . ' transition history disclosed reviewer identity details.');
    }
    $unqualified = new AcademicReviewerAuthorizationService($storage, []);
    issueWorkflowAssert(!$service->transition($reportId, 'REJECTED', $reviewer, $unqualified),
        $driver . ' unqualified reviewer changed a report.');
    try {
        $service->transition($reportId, 'UNDER_REVIEW', $reviewer, $authorization);
        throw new RuntimeException($driver . ' allowed an invalid transition out of RESOLVED.');
    } catch (InvalidArgumentException) {
    }

    $reviewedSource = $storage->find(QuestionIssueReportService::COLLECTION, $reportId);
    issueWorkflowAssert(is_array($reviewedSource) && count((array) ($reviewedSource['status_history'] ?? [])) === 3,
        $driver . ' status transition history was not persisted before restore reset.');
    $restored = AccountRestoreService::resetQuestionReportForRestoredCopy($reviewedSource, '2026-11-01T00:00:00+00:00');
    issueWorkflowAssert(($restored['status'] ?? '') === 'SUBMITTED'
        && ($restored['status_changed_at'] ?? '') === '2026-11-01T00:00:00+00:00'
        && ($restored['status_history'][0]['status'] ?? '') === 'SUBMITTED'
        && count($restored['status_history']) === 1,
        $driver . ' isolated restore retained a source reviewer decision history.');
};

try {
    $json = new JsonStorage($root . '/json');
    $exercise($json, 'json');

    $sqlitePath = $root . '/sqlite/fixture.sqlite';
    mkdir(dirname($sqlitePath), 0700, true);
    $pdo = new PDO('sqlite:' . $sqlitePath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('CREATE TABLE storage_records (collection TEXT NOT NULL, record_id TEXT NOT NULL, payload TEXT NOT NULL, created_at TEXT NOT NULL, updated_at TEXT NOT NULL, PRIMARY KEY(collection, record_id))');
    $exercise(new SqliteStorage($pdo), 'sqlite');

    $accountService = new AccountService();
    $registration = $accountService->register('qir-' . bin2hex(random_bytes(5)), 'Synthetic Workflow Test Password 734!');
    $account = (new AccountRepository())->findById((string) ($registration['account']['account_id'] ?? ''));
    issueWorkflowAssert(is_array($account), 'Synthetic learner account for deletion-lock verification was not created.');
    $learnerId = (string) $account['learner_id'];
    $questionId = 'q-deletion-lock';
    $attemptId = 'attempt-deletion-lock';
    \App\Core\App::storage()->create('attempts', [
        'id' => $attemptId, 'user_id' => $learnerId, 'completed' => true, 'board' => 'let', 'subject' => 'english',
        'review' => [['question' => ['id' => $questionId, 'question' => 'Synthetic locked snapshot.', 'taxonomy' => ['board_id' => 'let', 'subject_id' => 'english']]]],
    ]);
    (new AccountRepository())->updateById((string) $account['account_id'], static function (array $record): array {
        $record['status'] = 'deletion_requested';
        return $record;
    });
    try {
        (new QuestionIssueReportService(\App\Core\App::storage()))->submit(
            $learnerId, $attemptId, $questionId, 'OTHER', 'Must not outlive deletion.'
        );
        throw new RuntimeException('Question reporting wrote after the account deletion boundary began.');
    } catch (RuntimeException $exception) {
        issueWorkflowAssert(str_contains($exception->getMessage(), 'Account data is unavailable'),
            'Deletion-bound report write failed for an unrelated reason.');
    }
    issueWorkflowAssert(\App\Core\App::storage()->where(QuestionIssueReportService::COLLECTION, ['user_id' => $learnerId]) === [],
        'Account deletion lock left a question report written after deletion began.');

    $view = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Views/developer/question-issue-reports.php');
    issueWorkflowAssert(str_contains($view, 'aria-label="Question report status history"')
        && !str_contains($view, 'reviewer_account_id'), 'Reviewer queue did not expose a safe accessible status history.');
    echo "[PASS] Question report attempt ownership, archived snapshots, authorized status history, restore reset, JSON/SQLite compatibility, and account deletion locking.\n";
} finally {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($iterator as $entry) {
        if ($entry->isDir() && !$entry->isLink()) @rmdir($entry->getPathname());
        else @unlink($entry->getPathname());
    }
    @rmdir($root);
}
