<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();
require_once __DIR__ . '/MemoryStorage.php';

use App\Repositories\AttemptRepository;
use App\Services\AttemptService;
use App\Services\Quiz\Session\QuizSessionQuestion;
use Tools\Tests\MemoryStorage;

$validQuestion = [
    'id' => 'q-1', 'question' => 'A valid question?',
    'choices' => ['Yes', 'No'], 'answer' => 'Yes',
];

foreach ([null, 'legacy', ['id' => 'q'], array_merge($validQuestion, ['choices' => ['Only one']])]
    as $question) {
    if (QuizSessionQuestion::isRenderable($question)) {
        throw new RuntimeException('malformed session question was renderable');
    }
}

$_SESSION = [
    'questions' => [$validQuestion, ['id' => 'broken']],
    'answers' => ['q-1' => 'A'],
    'quiz_session' => ['id' => 'session-439'],
];
$read = \QuizResultSessionReader::read();
if ($read['questions'] !== []) {
    throw new RuntimeException('partially malformed session fabricated a partial result');
}

\SessionService::set('currentQuestion', ['stale']);
if (\QuizNavigationService::isCurrentValid([$validQuestion])) {
    throw new RuntimeException('malformed quiz cursor was accepted as the first question');
}

$storage = new MemoryStorage();
$attempts = new AttemptRepository($storage);
$service = new AttemptService($attempts);
$first = $service->save(['id' => 'attempt-first', 'session_id' => 'session-439', 'score' => 1]);
$retry = $service->save(['id' => 'attempt-retry', 'session_id' => 'session-439', 'score' => 1]);
if ($first !== $retry || count($attempts->all()) !== 1) {
    throw new RuntimeException('retry duplicated learner attempt persistence');
}

$_SESSION = [];
echo "[PASS] malformed quiz state is rejected before rendering or result persistence.\n";
echo "[PASS] learner attempt retries are idempotent by canonical session identity.\n";
