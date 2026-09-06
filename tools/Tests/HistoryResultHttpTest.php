<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

$root = dirname(__DIR__, 2);
$storagePath = sys_get_temp_dir() . '/boardprep-history-' . bin2hex(random_bytes(6));
putenv('APP_ENV=testing');
putenv('APP_STORAGE_PATH=' . $storagePath);
putenv('DB_DRIVER=json');

function historyCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

try {
    $_SESSION = [];
    \App\Core\App::boot();
    $learner = \App\Services\Learning\LearnerIdentityService::current();
    $repository = \App\Core\App::container()->get(\App\Repositories\AttemptRepository::class);
    $review = [
        [
            'question' => [
                'id' => 'history-q1',
                'question' => 'Which answer is correct?',
                'choices' => ['A', 'B'],
                'answer' => 'A',
                'explanation' => 'The first choice is supported.',
            ],
            'userAnswer' => 'B',
            'correctAnswer' => 'A',
            'correct' => false,
            'answered' => true,
        ],
        [
            'question' => [
                'id' => 'history-q2',
                'question' => 'Second question',
                'choices' => ['A', 'B'],
                'answer' => 'B',
                'explanation' => 'The second choice is supported.',
            ],
            'userAnswer' => 'B',
            'correctAnswer' => 'B',
            'correct' => true,
            'answered' => true,
        ],
    ];
    $owned = $repository->create([
        'id' => 'attempt-history-owned',
        'user_id' => $learner,
        'subject' => 'English',
        'topic' => 'Grammar',
        'mode' => 'practice',
        'difficulty' => 'mixed',
        'question_count' => 2,
        'question_ids' => ['history-q1', 'history-q2'],
        'score' => 1,
        'total' => 2,
        'percentage' => 50,
        'completed' => true,
        'completed_at' => '2026-09-06T10:00:00+00:00',
        'review' => $review,
    ]);
    $repository->create([
        'id' => 'attempt-history-foreign',
        'user_id' => 'learner-foreign',
        'score' => 2,
        'total' => 2,
        'percentage' => 100,
        'completed' => true,
    ]);
    $repository->create([
        'id' => 'attempt-history-partial',
        'user_id' => $learner,
        'score' => 0,
        'total' => 1,
        'percentage' => 0,
        'completed' => true,
    ]);

    $result = \App\Services\Quiz\HistoricalQuizResultService::findForLearner($owned['id']);
    historyCheck(is_array($result), 'owned historical result was not found');
    historyCheck($result['summary']['score'] === 1 && $result['summary']['total'] === 2, 'historical score was not preserved');
    historyCheck($result['review'][0]['userAnswer'] === 'B', 'historical selected answer was not preserved');
    historyCheck(\App\Services\Quiz\HistoricalQuizResultService::missedQuestionIds($result['review']) === ['history-q1'], 'historical missed question derivation was incorrect');
    historyCheck(\App\Services\Quiz\HistoricalQuizResultService::findForLearner('attempt-history-foreign') === null, 'foreign attempt was accessible');
    historyCheck(\App\Services\Quiz\HistoricalQuizResultService::findForLearner('attempt-history-unknown') === null, 'unknown attempt was not safely unavailable');
    historyCheck(\App\Services\Quiz\HistoricalQuizResultService::findForLearner('attempt-history-partial')['reviewUnavailable'] === true, 'partial attempt did not render as score-only');

    $before = json_encode($repository->find($owned['id']), JSON_THROW_ON_ERROR);
    \App\Services\Quiz\HistoricalQuizResultService::findForLearner($owned['id']);
    $after = json_encode($repository->find($owned['id']), JSON_THROW_ON_ERROR);
    historyCheck($before === $after, 'GET historical result mutated the attempt');

    $router = new \App\Core\Router();
    require $root . '/routes/web.php';
    $_GET = ['id' => $owned['id'], 'user_id' => 'attacker-controlled'];
    $_SERVER['REQUEST_METHOD'] = 'GET';
    ob_start();
    $router->dispatch('GET', '/history/result');
    $html = (string) ob_get_clean();
    historyCheck(str_contains($html, 'Historical Quiz Result'), 'history detail route did not render the historical result');
    historyCheck(str_contains($html, 'English') && str_contains($html, 'practice'), 'historical board or mode context was not rendered');
    historyCheck(str_contains($html, '1') && str_contains($html, '50'), 'historical score was not rendered');
    historyCheck(str_contains($html, 'Which answer is correct?'), 'historical question review was not rendered');
    historyCheck(str_contains($html, '/history') && str_contains($html, '/progress') && str_contains($html, '/dashboard'), 'historical return actions are incomplete');
    historyCheck(str_contains($html, '/flashcards/missed?attempt_id=attempt-history-owned'), 'historical missed-flashcard action is missing');
    historyCheck(str_contains(file_get_contents($root . '/app/Views/history/attempt.php'), 'View result'), 'history row reopen action is missing');

    echo "[PASS] Owned history result renders through the production router with score, review, study, flashcard, and return actions.\n";
    echo "[PASS] Foreign, unknown, request-owner override, and GET mutation boundaries hold.\n";
} finally {
    putenv('APP_ENV');
    putenv('APP_STORAGE_PATH');
    putenv('DB_DRIVER');
    $file = $storagePath . '/attempts.json';
    if (is_file($file)) {
        @unlink($file);
    }
    if (is_file($storagePath . '/.storage.lock')) {
        @unlink($storagePath . '/.storage.lock');
    }
    @rmdir($storagePath);
}
