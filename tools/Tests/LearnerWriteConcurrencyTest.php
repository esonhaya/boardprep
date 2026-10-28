<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();
require_once __DIR__ . '/Support/StaticStorageFixture.php';

use App\Core\App;
use App\Repositories\QuestionRepository;
use App\Repositories\FlashcardStateRepository;
use App\Services\Accounts\AccountRepository;
use App\Services\Flashcard\FlashcardDerivationService;
use App\Services\Flashcard\FlashcardRating;
use App\Services\Flashcard\FlashcardScheduler;
use App\Services\Flashcard\FlashcardService;
use App\Services\Learning\LearnerNotesService;
use App\Services\Learning\WeaknessService;
use App\Services\Question\QuestionStatisticsService;
use App\Storage\JsonStorage;

function learnerWriteAssert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$root = StaticStorageFixture::create(dirname(__DIR__, 2), 'batch723-learner-write-concurrency');
$parentPid = getmypid();
register_shutdown_function(static function () use ($root, $parentPid): void {
    if (getmypid() !== $parentPid) return;
    $real = realpath($root);
    $tmp = realpath(sys_get_temp_dir());
    if (!is_string($real) || !is_string($tmp) || !str_starts_with($real, $tmp . DIRECTORY_SEPARATOR)
        || !str_contains(basename($real), 'boardprep-batch723-learner-write-concurrency-')) return;
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
    'APP_ENV' => 'testing',
    'APP_DEBUG' => 'false',
    'APP_TIMEZONE' => 'Asia/Manila',
    'DB_DRIVER' => 'json',
    'APP_STORAGE_PATH' => $root,
    'APP_SESSION_PATH' => $root . '/sessions',
    'BOARDPREP_ACCOUNTS_ENABLED' => 'true',
] as $key => $value) {
    putenv($key . '=' . $value);
    $_ENV[$key] = $value;
}
$_SERVER['REMOTE_ADDR'] = '192.0.2.219';
App::boot();

$accountId = 'account-' . str_repeat('a', 32);
$learnerId = 'account-learner-' . str_repeat('b', 32);
$now = time();
$account = [
    'account_id' => $accountId,
    'learner_id' => $learnerId,
    'username' => 'synthetic-concurrency',
    'password_hash' => 'unused-synthetic-hash',
    'created_at' => gmdate(DATE_ATOM, $now),
    'updated_at' => gmdate(DATE_ATOM, $now),
    'auth_version' => 1,
    'status' => 'active',
    'deletion_requested_at' => null,
    'preferences' => [],
    'recovery_code_hashes' => [],
];
learnerWriteAssert((new AccountRepository())->create($account), 'Synthetic isolated account could not be created.');
$authSession = [
    'boardprep_account_auth' => [
        'account_id' => $accountId,
        'learner_id' => $learnerId,
        'auth_version' => 1,
        'started_at' => $now,
        'last_activity' => $now,
    ],
    'learner_identity' => $learnerId,
];
$_SESSION = $authSession;

$questions = App::container()->get(QuestionRepository::class);
$questions->all();
$baseQuestion = $questions->find('371');
learnerWriteAssert(is_array($baseQuestion), 'Canonical baseline question 371 is unavailable.');

if (!function_exists('pcntl_fork') || !function_exists('pcntl_waitpid')) {
    throw new RuntimeException('The local PHP runtime lacks supported process concurrency primitives.');
}

$runWorkers = static function (int $count, callable $work) use ($root, $authSession): void {
    $barrier = $root . '/workers-' . bin2hex(random_bytes(4)) . '.ready';
    $children = [];
    for ($index = 0; $index < $count; $index++) {
        $pid = pcntl_fork();
        if ($pid === -1) throw new RuntimeException('Unable to fork an isolated learner-write worker.');
        if ($pid === 0) {
            $_SESSION = $authSession;
            $deadline = microtime(true) + 10;
            while (!is_file($barrier)) {
                if (microtime(true) >= $deadline) exit(2);
                usleep(10000);
            }
            try {
                $work($index);
                exit(0);
            } catch (Throwable) {
                exit(1);
            }
        }
        $children[] = $pid;
    }
    file_put_contents($barrier, 'go');
    $pending = array_fill_keys($children, true);
    $statuses = [];
    $deadline = microtime(true) + 20;
    while ($pending !== [] && microtime(true) < $deadline) {
        foreach (array_keys($pending) as $pid) {
            $waited = pcntl_waitpid($pid, $status, WNOHANG);
            if ($waited === $pid) {
                $statuses[$pid] = $status;
                unset($pending[$pid]);
            }
        }
        if ($pending !== []) usleep(20000);
    }
    foreach (array_keys($pending) as $pid) {
        posix_kill($pid, SIGKILL);
        pcntl_waitpid($pid, $status);
        throw new RuntimeException('A concurrent learner-write worker timed out; possible lock deadlock.');
    }
    @unlink($barrier);
    foreach ($statuses as $status) {
        if (!pcntl_wifexited($status) || pcntl_wexitstatus($status) !== 0) {
            throw new RuntimeException('An isolated learner-write worker failed.');
        }
    }
};

$workers = 8;
$runWorkers($workers, static function (): void {
    WeaknessService::analyze([['topic' => 'Concurrency fixture', 'correct' => true]]);
});
$weakness = WeaknessService::all();
learnerWriteAssert(($weakness['Concurrency fixture']['correct'] ?? null) === $workers,
    'Concurrent exam finalizations lost learner weakness increments.');

$runWorkers($workers, static function (): void {
    QuestionStatisticsService::recordAnswers([['question_id' => '371', 'correct' => true]]);
});
$statistics = (new JsonStorage($root))->find('question-statistics', '371');
$expectedUsed = (int) ($baseQuestion['timesUsed'] ?? 0) + $workers;
$expectedCorrect = (int) ($baseQuestion['timesCorrect'] ?? 0) + $workers;
learnerWriteAssert(is_array($statistics)
    && (int) ($statistics['timesUsed'] ?? -1) === $expectedUsed
    && (int) ($statistics['timesCorrect'] ?? -1) === $expectedCorrect,
    'Concurrent finalizations lost shared question-statistics increments.');

$notes = new LearnerNotesService();
$createRequestId = bin2hex(random_bytes(16));
$createNoteInput = [
    'create_request_id' => $createRequestId,
    'title' => 'Concurrent note',
    'board_id' => 'let',
    'body' => 'Saved once despite duplicate requests.',
];
$createdNote = $notes->save($createNoteInput);
$runWorkers($workers, static function () use ($notes, $createNoteInput): void {
    $saved = $notes->save($createNoteInput);
    if (($saved['create_request_id'] ?? null) !== $createNoteInput['create_request_id']) {
        throw new RuntimeException('A concurrent exact create retry did not return its stored note.');
    }
});
$createdMatches = array_values(array_filter($notes->all(), static function (array $note) use ($createRequestId): bool {
    return ($note['create_request_id'] ?? null) === $createRequestId;
}));
learnerWriteAssert(count($createdMatches) === 1
    && ($createdMatches[0]['id'] ?? null) === $createdNote['id'],
    'Concurrent exact note-create retries produced multiple notes.');
$noteUpdate = [
    'note_id' => $createdNote['id'],
    'note_revision' => $createdNote['note_revision'],
    'title' => 'Concurrent note, edited',
    'board_id' => 'let',
    'body' => 'The same edit was submitted concurrently.',
];
$runWorkers($workers, static function () use ($notes, $noteUpdate): void {
    $saved = $notes->save($noteUpdate);
    if (($saved['note_revision'] ?? null) !== 2
        || ($saved['body'] ?? null) !== $noteUpdate['body']) {
        throw new RuntimeException('A concurrent exact note retry did not confirm the single saved revision.');
    }
});
$authoritativeNote = $notes->find((string) $createdNote['id']);
learnerWriteAssert(is_array($authoritativeNote)
    && ($authoritativeNote['note_revision'] ?? null) === 2
    && ($authoritativeNote['body'] ?? null) === $noteUpdate['body'],
    'Concurrent exact note retries created multiple revisions or lost the saved edit.');

$flashcardStates = new FlashcardStateRepository(App::storage());
$flashcardDerivation = new FlashcardDerivationService($questions);
$flashcard = $flashcardDerivation->forQuestionId('371');
learnerWriteAssert(is_array($flashcard), 'Canonical flashcard fixture 371 is unavailable.');
$flashcards = new FlashcardService($flashcardDerivation, $flashcardStates, new FlashcardScheduler());
$reviewToken = bin2hex(random_bytes(16));
$reviewedAt = new DateTimeImmutable('2026-10-29T00:00:00+08:00');
$runWorkers($workers, static function () use ($flashcards, $flashcard, $reviewToken, $reviewedAt): void {
    $result = $flashcards->review(
        (string) $flashcard['card_key'],
        FlashcardRating::GOOD,
        $reviewedAt,
        null,
        $reviewToken,
        0
    );
    if (($result['review_count'] ?? null) !== 1) {
        throw new RuntimeException('A concurrent exact flashcard retry did not return the original review result.');
    }
});
$flashcardState = $flashcardStates->find((string) $flashcard['card_key']);
learnerWriteAssert(is_array($flashcardState) && (int) ($flashcardState['review_count'] ?? -1) === 1,
    'Concurrent exact flashcard retries advanced scheduling more than once.');

echo "[PASS] Eight concurrent isolated account writes preserved weakness and question-statistics increments, one idempotent note create and edit, and one idempotent flashcard review.\n";
