<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

$driver = strtolower(trim((string) ($argv[1] ?? 'json')));
if (!in_array($driver, ['json', 'sqlite'], true)) {
    throw new InvalidArgumentException('Pass json or sqlite as the storage driver.');
}
if ($driver === 'sqlite' && !extension_loaded('pdo_sqlite')) {
    throw new RuntimeException('SQLite result parity test requires pdo_sqlite.');
}

$fixture = sys_get_temp_dir() . '/boardprep-result-backend-' . bin2hex(random_bytes(8));
$storage = $fixture . '/storage';
if (!mkdir($storage, 0700, true) && !is_dir($storage)) {
    throw new RuntimeException('Unable to create an isolated result-storage fixture.');
}

putenv('APP_ENV=testing');
putenv('APP_DEBUG=false');
putenv('DB_DRIVER=' . $driver);
putenv('APP_STORAGE_PATH=' . $storage);
putenv('DB_SQLITE_PATH=' . $storage . '/boardprep.sqlite');
putenv('BOARDPREP_ACCOUNTS_ENABLED=false');
$_ENV['APP_ENV'] = 'testing';
$_ENV['APP_DEBUG'] = 'false';
$_ENV['DB_DRIVER'] = $driver;
$_ENV['APP_STORAGE_PATH'] = $storage;
$_ENV['DB_SQLITE_PATH'] = $storage . '/boardprep.sqlite';
$_ENV['BOARDPREP_ACCOUNTS_ENABLED'] = 'false';
$_SESSION = [];

$removeTree = static function (string $path): void {
    if (!is_dir($path)) {
        if (is_file($path)) @unlink($path);
        return;
    }
    $entries = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($entries as $entry) {
        $entry->isDir() ? @rmdir($entry->getPathname()) : @unlink($entry->getPathname());
    }
    @rmdir($path);
};

try {
    \App\Core\App::boot();
    $sessionId = 'quiz-' . bin2hex(random_bytes(8));
    $questions = [
        [
            'id' => 'batch739-backend-q1',
            'question' => 'Which fixture answer is correct?',
            'choices' => ['First choice', 'Second choice'],
            'answer' => 'First choice',
            'explanation' => 'The first choice is correct.',
            'board' => 'LET',
            'subject' => 'English',
            'topic' => 'Grammar',
        ],
        [
            'id' => 'batch739-backend-q2',
            'question' => 'Which fixture answer remains unanswered?',
            'choices' => ['First choice', 'Second choice'],
            'answer' => 'Second choice',
            'explanation' => 'The second choice is correct.',
            'board' => 'LET',
            'subject' => 'English',
            'topic' => 'Grammar',
        ],
    ];
    $session = [
        'id' => $sessionId,
        'board' => 'LET',
        'subject' => 'English',
        'mode' => 'exam',
        'session_type' => 'exam_simulation',
        'difficulty' => 'mixed',
        'timer_mode' => 'untimed',
        'time_limit_minutes' => 0,
        'time_limit_seconds' => 0,
        'time_expired' => false,
        'started_at' => date('c'),
        'question_count' => 2,
        'requested_question_count' => 2,
    ];
    \SessionService::set('quiz_session', $session);
    \SessionService::set('questions', $questions);
    \SessionService::set('answers', ['batch739-backend-q1' => 'A']);
    \SessionService::set('mode', 'exam');
    \SessionService::set('exam_review_flags', [1 => true]);
    \SessionService::set('exam_review_flag_versions', [1 => 1]);
    \SessionService::set('exam_review_flag_session_id', $sessionId);

    $result = QuizResultService::build();
    $summary = $result['summary'] ?? [];
    $learnerId = \App\Services\Learning\LearnerIdentityService::current();
    $attempts = \App\Core\App::container()->get(\App\Repositories\AttemptRepository::class);
    $saved = $attempts->bySessionId($sessionId, $learnerId);
    if (($summary['score'] ?? null) !== 1 || ($summary['total'] ?? null) !== 2
        || ($summary['unanswered'] ?? null) !== 1
        || !is_array($saved) || (int) ($saved['score'] ?? -1) !== 1
        || (int) ($saved['answered'] ?? -1) !== 1
        || (int) ($saved['question_count'] ?? -1) !== 2
        || (int) ($saved['unanswered'] ?? -1) !== 1
        || count((array) ($saved['review'] ?? [])) !== 2
        || ($saved['review_flag_snapshot'] ?? null) !== [
            'version' => 1,
            'marked_question_ids' => ['batch739-backend-q2'],
        ]) {
        throw new RuntimeException("{$driver} result persistence changed scores, unanswered totals, review rows, or ownership: " . json_encode([
            'summary' => $summary,
            'learner_id' => $learnerId,
            'saved' => $saved,
        ]));
    }

    $beforeRepeat = [
        'attempts' => $attempts->byUser($learnerId),
        'statistics' => \App\Core\App::storage()->all('question-statistics'),
        'weakness' => \App\Core\App::storage()->all('weakness'),
    ];
    QuizResultService::build();
    $afterRepeat = [
        'attempts' => $attempts->byUser($learnerId),
        'statistics' => \App\Core\App::storage()->all('question-statistics'),
        'weakness' => \App\Core\App::storage()->all('weakness'),
    ];
    if ($beforeRepeat !== $afterRepeat || count($afterRepeat['attempts']) !== 1) {
        throw new RuntimeException("{$driver} repeated result read duplicated an attempt or learner-statistics write.");
    }

    echo "[PASS] {$driver} exam result preserved ownership, exact score, unanswered count, review flags, history row, and idempotent repeat reads.\n";
} finally {
    $removeTree($fixture);
    foreach (['APP_ENV', 'APP_DEBUG', 'DB_DRIVER', 'APP_STORAGE_PATH', 'DB_SQLITE_PATH', 'BOARDPREP_ACCOUNTS_ENABLED'] as $key) {
        putenv($key);
        unset($_ENV[$key]);
    }
}
