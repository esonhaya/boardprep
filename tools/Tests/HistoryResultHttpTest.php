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
                'explanation' => '',
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
        'review_flag_snapshot' => ['version' => 1, 'marked_question_ids' => ['history-q1']],
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
    $repository->create([
        'id' => 'attempt-history-legacy-exam',
        'user_id' => $learner,
        'board' => 'LET',
        'subject' => 'English',
        'mode' => 'exam',
        'score' => 1,
        'total' => 2,
        'percentage' => 50,
        'completed' => true,
        'review' => $review,
    ]);
    $historyStart = new DateTimeImmutable('2027-01-01T10:00:00+00:00');
    for ($index = 1; $index <= 497; $index++) {
        $isLetAttempt = $index % 2 === 1;
        $repository->create([
            'id' => 'attempt-history-page-' . $index,
            'user_id' => $learner,
            'board' => $isLetAttempt ? 'LET' : 'CSE',
            'subject' => $isLetAttempt ? 'English' : 'verbal-ability',
            'mode' => 'practice',
            'score' => 1,
            'total' => 2,
            'percentage' => 50,
            'completed' => true,
            'completed_at' => $historyStart->modify('+' . $index . ' days')->format(DATE_ATOM),
        ]);
    }

    $result = \App\Services\Quiz\HistoricalQuizResultService::findForLearner($owned['id']);
    historyCheck(is_array($result), 'owned historical result was not found');
    historyCheck($result['summary']['score'] === 1 && $result['summary']['total'] === 2, 'historical score was not preserved');
    historyCheck($result['review'][0]['userAnswer'] === 'B', 'historical selected answer was not preserved');
    historyCheck(\App\Services\Quiz\HistoricalQuizResultService::markedQuestionIds($result['attempt'], $result['review']) === ['history-q1'], 'saved result review marks were not read from the owned attempt');
    historyCheck(\App\Services\Quiz\HistoricalQuizResultService::missedQuestionIds($result['review']) === ['history-q1'], 'historical missed question derivation was incorrect');
    historyCheck(\App\Services\Quiz\HistoricalQuizResultService::findForLearner('attempt-history-foreign') === null, 'foreign attempt was accessible');
    historyCheck(\App\Services\Quiz\HistoricalQuizResultService::findForLearner('attempt-history-unknown') === null, 'unknown attempt was not safely unavailable');
    historyCheck(\App\Services\Quiz\HistoricalQuizResultService::findForLearner('attempt-history-partial')['reviewUnavailable'] === true, 'partial attempt did not render as score-only');
    $legacy = \App\Services\Quiz\HistoricalQuizResultService::findForLearner('attempt-history-legacy-exam');
    historyCheck(is_array($legacy) && \App\Services\Quiz\HistoricalQuizResultService::markedQuestionIds($legacy['attempt'], $legacy['review']) === null, 'legacy exam without a snapshot was assigned invented review marks');

    $before = json_encode($repository->find($owned['id']), JSON_THROW_ON_ERROR);
    \App\Services\Quiz\HistoricalQuizResultService::findForLearner($owned['id']);
    $after = json_encode($repository->find($owned['id']), JSON_THROW_ON_ERROR);
    historyCheck($before === $after, 'GET historical result mutated the attempt');

    $router = new \App\Core\Router();
    require $root . '/routes/web.php';
    $_GET = ['id' => $owned['id'], 'user_id' => 'attacker-controlled', 'review_filter' => 'marked'];
    $_SERVER['REQUEST_METHOD'] = 'GET';
    ob_start();
    $router->dispatch('GET', '/history/result');
    $html = (string) ob_get_clean();
    historyCheck(str_contains($html, 'Historical Quiz Result'), 'history detail route did not render the historical result');
    historyCheck(str_contains($html, 'English') && str_contains($html, 'practice'), 'historical board or mode context was not rendered');
    historyCheck(str_contains($html, '1') && str_contains($html, '50'), 'historical score was not rendered');
    historyCheck(str_contains($html, 'Which answer is correct?'), 'historical question review was not rendered');
    historyCheck(str_contains($html, '/history') && str_contains($html, '/progress') && str_contains($html, '/dashboard'), 'historical return actions are incomplete');
    historyCheck(str_contains($html, 'Marked for review (1)') && str_contains($html, 'Which answer is correct?'), 'saved marked result filter was not rendered for its owner');
    historyCheck(str_contains($html, '/flashcards/missed?attempt_id=attempt-history-owned'), 'historical missed-flashcard action is missing');
    historyCheck(str_contains($html, '/question-report?attempt_id=attempt-history-owned&amp;question_id=history-q1'),
        'historical result question report did not bind both the owned attempt and reviewed question');
    historyCheck(str_contains(file_get_contents($root . '/app/Views/history/attempt.php'), 'View result'), 'history row reopen action is missing');

    $_GET = ['id' => $owned['id'], 'review_filter' => 'all'];
    ob_start();
    $router->dispatch('GET', '/history/result');
    $allReviewHtml = (string) ob_get_clean();
    historyCheck(str_contains($allReviewHtml, 'No explanation is currently available for this question.'),
        'missing canonical explanation was rendered as an unexplained blank result block');

    $_GET = ['id' => 'attempt-history-legacy-exam', 'review_filter' => 'marked'];
    ob_start();
    $router->dispatch('GET', '/history/result');
    $legacyHtml = (string) ob_get_clean();
    historyCheck(str_contains($legacyHtml, 'Review marks are unavailable for this attempt. No marks were reconstructed.'), 'legacy exam did not disclose unavailable review marks');
    historyCheck(!str_contains($legacyHtml, 'Marked for review ('), 'legacy result exposed a fabricated marked-question filter');

    $_GET = [];
    ob_start();
    $router->dispatch('GET', '/history');
    $firstHistoryPage = (string) ob_get_clean();
    historyCheck(substr_count($firstHistoryPage, '<article class="quiz-history-item">') === 20, 'quiz history did not bound the first rendered page to 20 attempts');
    historyCheck(str_contains($firstHistoryPage, '<strong>1/2</strong> · 50%')
        && !str_contains($firstHistoryPage, '<strong>50%</strong> — 50%'), 'history row did not present score and percentage without repeating the same percentage');
    historyCheck(str_contains($firstHistoryPage, 'Showing 1–20 of 500 completed quizzes')
        && str_contains($firstHistoryPage, 'Page 1 of 25')
        && str_contains($firstHistoryPage, 'href="/history?page=2"'), '500-attempt history pagination did not show its range, page count, or next destination');
    historyCheck(str_contains($firstHistoryPage, 'href="/history/result?id=attempt-history-page-497&amp;return_to=%2Fhistory%3Fpage%3D1"')
        && !str_contains($firstHistoryPage, 'id=attempt-history-page-1&amp;return_to='), 'first history page did not prioritize the newest completed attempts');

    $_GET = ['page' => '25'];
    ob_start();
    $router->dispatch('GET', '/history');
    $lastHistoryPage = (string) ob_get_clean();
    historyCheck(substr_count($lastHistoryPage, '<article class="quiz-history-item">') === 20, '500-attempt history last page did not stay bounded to 20 rendered attempts');
    historyCheck(str_contains($lastHistoryPage, 'Showing 481–500 of 500 completed quizzes')
        && str_contains($lastHistoryPage, 'Page 25 of 25')
        && str_contains($lastHistoryPage, 'href="/history?page=24"'), 'last history page did not show its range or previous destination');
    historyCheck(str_contains($lastHistoryPage, 'id=attempt-history-page-1&amp;return_to=%2Fhistory%3Fpage%3D25')
        && str_contains($lastHistoryPage, 'id=attempt-history-owned&amp;return_to=%2Fhistory%3Fpage%3D25')
        && !str_contains($lastHistoryPage, 'id=attempt-history-page-497&amp;return_to='), 'last history page did not preserve the oldest and original owned attempts');

    $_GET = ['page' => '999'];
    ob_start();
    $router->dispatch('GET', '/history');
    $clampedHistoryPage = (string) ob_get_clean();
    historyCheck(str_contains($clampedHistoryPage, 'Page 25 of 25')
        && str_contains($clampedHistoryPage, 'Showing 481–500 of 500 completed quizzes'), 'out-of-range history page did not clamp to the last available page');

    $_GET = ['page' => '2', 'board' => 'LET', 'subject' => 'English'];
    ob_start();
    $router->dispatch('GET', '/history');
    $letHistoryPage = (string) ob_get_clean();
    historyCheck(substr_count($letHistoryPage, '<article class="quiz-history-item">') === 20
        && str_contains($letHistoryPage, 'Showing 21–40 of 250 completed quizzes')
        && str_contains($letHistoryPage, 'href="/history?page=1&amp;board=let&amp;subject=english"')
        && str_contains($letHistoryPage, 'value="let" selected')
        && str_contains($letHistoryPage, 'value="english" selected')
        && str_contains($letHistoryPage, '<strong>250</strong>'), 'registered LET/English filters did not preserve the filtered count, summary, selection, or pagination context');

    $_GET = ['board' => 'civil-service', 'subject' => 'verbal-ability'];
    ob_start();
    $router->dispatch('GET', '/history');
    $cseHistory = (string) ob_get_clean();
    historyCheck(str_contains($cseHistory, 'Showing 248 completed quizzes for these filters')
        && substr_count($cseHistory, '<article class="quiz-history-item">') === 20, 'CSE/Verbal Ability history filter did not use the recorded registered taxonomy');

    $_GET = ['board' => \App\Services\Learning\CompletedAttemptHistoryFilter::UNREGISTERED];
    ob_start();
    $router->dispatch('GET', '/history');
    $unregisteredBoardHistory = (string) ob_get_clean();
    historyCheck(str_contains($unregisteredBoardHistory, 'Showing 2 completed quizzes for these filters')
        && str_contains($unregisteredBoardHistory, 'Unrecorded or unregistered board (2)'), 'unrecorded board history was not kept visible as an explicit legacy bucket');

    $_GET = ['subject' => \App\Services\Learning\CompletedAttemptHistoryFilter::UNREGISTERED];
    ob_start();
    $router->dispatch('GET', '/history');
    $unregisteredSubjectHistory = (string) ob_get_clean();
    historyCheck(str_contains($unregisteredSubjectHistory, 'Showing 1 completed quiz for these filters')
        && str_contains($unregisteredSubjectHistory, 'Unrecorded or unregistered subject (1)'), 'unrecorded subject history was not accurately disclosed');

    $_GET = ['board' => 'not-a-registered-board', 'subject' => 'not-a-registered-subject'];
    ob_start();
    $router->dispatch('GET', '/history');
    $invalidFilterHistory = (string) ob_get_clean();
    historyCheck(str_contains($invalidFilterHistory, 'An unrecognized board or subject filter was ignored.')
        && str_contains($invalidFilterHistory, 'Showing 1–20 of 500 completed quizzes'), 'invalid filter input did not safely return to the complete owned history');

    $_GET = ['page' => '13', 'board' => 'let', 'subject' => 'english'];
    ob_start();
    $router->dispatch('GET', '/history');
    $lastLetHistoryPage = (string) ob_get_clean();
    historyCheck(str_contains($lastLetHistoryPage, 'Showing 241–250 of 250 completed quizzes · Page 13 of 13')
        && str_contains($lastLetHistoryPage, 'id=attempt-history-page-1&amp;return_to=%2Fhistory%3Fpage%3D13%26board%3Dlet%26subject%3Denglish'), 'filtered older attempts did not retain their exact page and filter return context');

    $_GET = ['id' => 'attempt-history-page-1', 'return_to' => '/history?page=999&board=LET&subject=English'];
    ob_start();
    $router->dispatch('GET', '/history/result');
    $historyReturnHtml = (string) ob_get_clean();
    historyCheck(str_contains($historyReturnHtml, 'href="/history?page=13&amp;board=let&amp;subject=english"'), 'saved result did not clamp and restore its valid owned history return destination');

    $_GET = ['id' => 'attempt-history-page-1', 'return_to' => '//evil.example/history'];
    ob_start();
    $router->dispatch('GET', '/history/result');
    $unsafeReturnHtml = (string) ob_get_clean();
    historyCheck(str_contains($unsafeReturnHtml, 'href="/history"') && !str_contains($unsafeReturnHtml, 'evil.example'), 'historical result accepted an external return destination');

    $ownedHistoryAttempts = $repository->byUser($learner);
    $foreignAttempts = array_values(array_filter(
        $repository->all(),
        static fn(array $attempt): bool => (string) ($attempt['user_id'] ?? '') !== $learner
    ));
    historyCheck(count($ownedHistoryAttempts) === 500 && count($foreignAttempts) === 1, 'history count fixtures lost their owner boundary');
    foreach ([0, 1, 20, 100, 500] as $targetCount) {
        $repository->replaceAll(array_merge($foreignAttempts, array_slice($ownedHistoryAttempts, 0, $targetCount)));
        historyCheck(count($repository->byUser($learner)) === $targetCount, 'persisted synthetic history count was not ' . $targetCount);
        $_GET = [];
        ob_start();
        $router->dispatch('GET', '/history');
        $countHistory = (string) ob_get_clean();
        historyCheck(substr_count($countHistory, '<article class="quiz-history-item">') === min(20, $targetCount), 'history row bound was incorrect for ' . $targetCount . ' attempts');
        if ($targetCount === 0) {
            historyCheck(str_contains($countHistory, 'No completed quizzes yet')
                && !str_contains($countHistory, 'attempt-history-foreign'), 'empty owned history exposed a foreign attempt or missed its empty state');
        } elseif ($targetCount <= 20) {
            $label = $targetCount === 1 ? 'Showing 1 completed quiz.' : 'Showing 20 completed quizzes.';
            historyCheck(str_contains($countHistory, $label), 'single-page history status was incorrect for ' . $targetCount . ' attempts');
        } else {
            historyCheck(str_contains($countHistory, 'Showing 1–20 of ' . $targetCount . ' completed quizzes'), 'history pagination total was incorrect for ' . $targetCount . ' attempts');
        }
    }

    echo "[PASS] Owned history result renders through the production router with score, review, study, flashcard, result-bound report, and return actions.\n";
    echo "[PASS] Persisted owned histories with 0, 1, 20, 100, and 500 attempts render bounded rows, accurate summaries, and safe empty states.\n";
    echo "[PASS] Registered mixed-board/subject filters, legacy unregistered buckets, invalid filters, bounded pagination, and safe result-to-history return paths hold.\n";
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
