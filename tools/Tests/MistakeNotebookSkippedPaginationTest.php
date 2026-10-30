<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

$root = dirname(__DIR__, 2);
$storagePath = sys_get_temp_dir() . '/boardprep-skipped-history-' . bin2hex(random_bytes(6));
if (!mkdir($storagePath, 0700, true)) {
    throw new RuntimeException('Unable to create an isolated skipped-history storage fixture.');
}
putenv('APP_ENV=testing');
putenv('APP_DEBUG=false');
putenv('APP_STORAGE_PATH=' . $storagePath);
putenv('DB_DRIVER=json');
putenv('APP_SESSION_PATH=' . $storagePath . '/sessions');

function skippedHistoryAssert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function skippedHistoryRender(\App\Core\Router $router, array $query): string
{
    $_GET = $query;
    $_SERVER['REQUEST_METHOD'] = 'GET';
    ob_start();
    $router->dispatch('GET', '/mistake-notebook');
    return (string) ob_get_clean();
}

try {
    $_SESSION = [];
    @mkdir($storagePath . '/sessions', 0700, true);
    \App\Core\App::boot();
    $learner = \App\Services\Learning\LearnerIdentityService::current();
    $repository = \App\Core\App::container()->get(\App\Repositories\AttemptRepository::class);
    $router = new \App\Core\Router();
    require $root . '/routes/web.php';

    $empty = skippedHistoryRender($router, []);
    skippedHistoryAssert(str_contains($empty, 'There are no recorded mistakes yet')
        && !str_contains($empty, 'Skipped question pages'), 'Empty skipped history did not stay empty.');

    $owned = [];
    $start = new DateTimeImmutable('2025-01-01T00:00:00+00:00');
    for ($index = 0; $index < 500; $index++) {
        $questionId = sprintf('skipped-q-%03d', $index);
        $question = [
            'id' => $questionId,
            'question' => 'Synthetic unanswered question ' . $index,
            'choices' => ['A', 'B'],
            'answer' => 'A',
            'explanation' => 'Synthetic saved explanation.',
            'taxonomy' => ['board_id' => 'let', 'subject_id' => 'english', 'topic_id' => 'synthetic-topic'],
        ];
        $owned[] = [
            'id' => 'attempt-skipped-' . sprintf('%03d', $index),
            'user_id' => $learner,
            'board' => 'let',
            'subject' => 'english',
            'score' => 0,
            'total' => 1,
            'percentage' => 0,
            'completed' => true,
            'completed_at' => $start->modify('+' . $index . ' days')->format(DATE_ATOM),
            'review' => [[
                'question' => $question,
                'choices' => ['A', 'B'],
                'userAnswer' => '',
                'correctAnswer' => 'A',
                'correct' => false,
                'answered' => false,
                'explanation' => 'Synthetic saved explanation.',
            ]],
        ];
    }
    $foreignQuestion = $owned[0]['review'][0]['question'];
    $foreignQuestion['id'] = 'foreign-private-marker';
    $foreign = $owned[0];
    $foreign['id'] = 'attempt-skipped-foreign';
    $foreign['user_id'] = 'learner-foreign';
    $foreign['review'][0]['question'] = $foreignQuestion;
    $repository->replaceAll([$foreign]);

    foreach ([0, 1, 20, 100, 500] as $count) {
        $repository->replaceAll(array_merge([$foreign], array_slice($owned, 0, $count)));
        $html = skippedHistoryRender($router, []);
        $linkCount = preg_match_all('#href="/mistake-notebook/review\?attempt_id=#', $html);
        skippedHistoryAssert($linkCount === min(20, $count), 'Skipped review links were not bounded for ' . $count . ' saved attempts.');
        skippedHistoryAssert(!str_contains($html, 'foreign-private-marker'), 'Foreign skipped history appeared in an owned notebook.');
        if ($count === 0) {
            skippedHistoryAssert(!str_contains($html, 'Skipped questions'), 'A zero-attempt learner saw a skipped-question section.');
        } elseif ($count === 1) {
            skippedHistoryAssert(str_contains($html, 'Showing 1–1 of 1 skipped questions'), 'One skipped question had an inaccurate range.');
        } elseif ($count === 20) {
            skippedHistoryAssert(str_contains($html, 'Showing 1–20 of 20 skipped questions'), 'Twenty skipped questions had an inaccurate range.');
        } elseif ($count === 100) {
            skippedHistoryAssert(str_contains($html, 'Showing 1–20 of 100 skipped questions')
                && str_contains($html, 'skipped_page=2'), 'One hundred skipped questions lacked bounded forward navigation.');
        } else {
            skippedHistoryAssert(str_contains($html, 'Showing 1–20 of 500 skipped questions')
                && str_contains($html, 'Page 1 of 25')
                && str_contains($html, 'question_id=skipped-q-499')
                && !str_contains($html, 'question_id=skipped-q-000'), 'The newest page of a 500-item skipped history was incorrect.');
            $lastPage = skippedHistoryRender($router, ['skipped_page' => '25']);
            skippedHistoryAssert(str_contains($lastPage, 'Showing 481–500 of 500 skipped questions')
                && str_contains($lastPage, 'Page 25 of 25')
                && str_contains($lastPage, 'question_id=skipped-q-000')
                && str_contains($lastPage, 'question_id=skipped-q-019')
                && !str_contains($lastPage, 'question_id=skipped-q-499'), 'Older skipped history did not retain its owned review links.');
            $clamped = skippedHistoryRender($router, ['skipped_page' => '999999']);
            skippedHistoryAssert(str_contains($clamped, 'Page 25 of 25'), 'Out-of-range skipped-history pagination did not clamp to the last page.');
        }
    }

    echo "[PASS] Persisted skipped-question history is bounded to 20 review links across 0, 1, 20, 100, and 500 attempts.\n";
    echo "[PASS] Older skipped reviews remain reachable through owner-scoped, clamped pagination; foreign records stay hidden.\n";
} finally {
    putenv('APP_ENV');
    putenv('APP_DEBUG');
    putenv('APP_STORAGE_PATH');
    putenv('DB_DRIVER');
    putenv('APP_SESSION_PATH');
    $real = realpath($storagePath);
    $temporaryRoot = realpath(sys_get_temp_dir());
    if (is_string($real) && is_string($temporaryRoot)
        && str_starts_with($real, $temporaryRoot . DIRECTORY_SEPARATOR)
        && str_starts_with(basename($real), 'boardprep-skipped-history-')) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($real, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $entry) {
            if ($entry->isDir() && !$entry->isLink()) @rmdir($entry->getPathname());
            else @unlink($entry->getPathname());
        }
        @rmdir($real);
    }
}
