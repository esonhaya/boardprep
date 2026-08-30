<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . "/app/Core/Autoloader.php";
\App\Core\Autoloader::register();

use App\Services\Quiz\Start\QuizStartSessionWriter;

$_SESSION = [];
\SessionService::set('questions', 'stale');
\SessionService::set('answers', ['bad']);
\SessionService::set('quiz_session', 'bad');
$read = \QuizResultSessionReader::read();
if ($read['questions'] !== [] || $read['answers'] !== [] || $read['session'] !== []) {
    throw new RuntimeException('malformed session containers were trusted');
}

\SessionService::set('questions', [[
    'id' => 'q1',
    'question' => 'One plus one?',
    'choices' => ['1', '2'],
    'answer' => '2',
]]);
\SessionService::set('answers', ['q1' => ['malformed']]);
$score = \QuizScoringService::calculate(
    \SessionService::get('questions'),
    \SessionService::get('answers')
);
if (($score['score'] ?? -1) !== 0 || ($score['total'] ?? -1) !== 1) {
    throw new RuntimeException('malformed answer changed scoring safely');
}

\SessionService::set('answers', []);
$reflection = new ReflectionMethod(\QuizSubmissionService::class, 'storeAnswer');
$question = \SessionService::get('questions')[0];
if ($reflection->invoke(null, $question, 'B') !== true
    || $reflection->invoke(null, $question, 'A') !== false
    || \SessionService::get('answers')['q1'] !== 'B') {
    throw new RuntimeException('duplicate submission overwrote the recorded answer');
}

\SessionService::set('attempt_persisted', true);
\QuizResultPersistenceService::persist([], [], [], []);
if (!\SessionService::has('attempt_persisted')) {
    throw new RuntimeException('persistence guard did not remain idempotent');
}
\SessionService::set('quiz_result', ['stale' => true]);
$stale = \QuizResultService::build();
if (!is_array($stale) || !array_key_exists('summary', $stale)) {
    throw new RuntimeException('malformed cached result was trusted');
}

$spec = new \QuizSpecification(
    board: 'LET', subject: 'English', domain: null, topics: ['Grammar'], concepts: [],
    difficulty: 'mixed', questionCount: 1, mode: 'practice', adaptive: false,
    shuffle: true, boardBlueprintVersion: null, subjectBlueprintVersion: null
);
\SessionService::set('attempt_persisted', true);
\SessionService::set('quiz_completed', true);
\SessionService::set('quiz_result', ['summary' => 'stale']);
QuizStartSessionWriter::write($spec, [$question]);
if (\SessionService::has('attempt_persisted') || \SessionService::has('quiz_completed')
    || \SessionService::has('quiz_result') || \SessionService::get('answers') !== []) {
    throw new RuntimeException('new quiz did not clear stale completion state');
}

echo "[PASS] quiz session state and answer storage are resilient and idempotent.\n";
echo "[PASS] malformed result state is safe and new quizzes reset completion markers.\n";
