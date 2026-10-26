<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
putenv('APP_ENV=production');
putenv('APP_DEBUG=false');
putenv('DB_DRIVER=json');
putenv('APP_STORAGE_PATH=' . $root . '/storage');
require_once $root . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

use App\Services\Question\QuestionQueryService;

$started = hrtime(true);
$firstPage = QuestionQueryService::getQuestionPage([], 1, 50, 'clear', []);
if ($firstPage['canonical_total'] !== 9123
    || $firstPage['total'] !== 9123
    || count($firstPage['items']) !== 50
    || $firstPage['pages'] !== 183) {
    throw new RuntimeException('The no-detected-technical-issue view did not retain bounded pagination for the full canonical bank.');
}

$probeId = (string) ($firstPage['items'][0]['id'] ?? '');
if ($probeId === '') {
    throw new RuntimeException('The canonical inventory did not return an ID for the in-memory filter contract.');
}
$issuePage = QuestionQueryService::getQuestionPage([], 1, 50, 'issue', [$probeId]);
$clearPage = QuestionQueryService::getQuestionPage([], 1, 50, 'clear', [$probeId]);
if ($issuePage['total'] !== 1
    || (string) ($issuePage['items'][0]['id'] ?? '') !== $probeId
    || $clearPage['total'] !== 9122
    || in_array($probeId, array_map(static fn(array $question): string => (string) ($question['id'] ?? ''), $clearPage['items']), true)) {
    throw new RuntimeException('Technical-issue and no-detected-technical-issue filters did not apply before pagination.');
}

$elapsedMs = round((hrtime(true) - $started) / 1_000_000, 2);
echo 'QUESTION_QUERY_TECHNICAL_FILTER_PASS=1 CANONICAL=9123 PAGE_SIZE=50 PAGES=183 ELAPSED_MS=' . $elapsedMs . PHP_EOL;
