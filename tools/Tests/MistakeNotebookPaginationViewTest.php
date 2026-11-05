<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

$items = [];
$skipped = [[
    'attempt_id' => 'synthetic-attempt',
    'question_id' => 'synthetic-question',
    'board' => '',
    'subject' => '',
    'count' => 1,
]];
$boards = $subjects = $topics = $topicSummary = $filters = [];
$csrfToken = '';
$totalMistakes = $filteredCount = 0;
$skippedTotal = 21;
$skippedPage = 2;
$skippedPages = 2;
$skippedPageSize = 20;
$page = 1;
$pages = 1;

ob_start();
include dirname(__DIR__, 2) . '/app/Views/learning/mistake-notebook.php';
$html = (string) ob_get_clean();

$paginationLandmarks = substr_count($html, 'aria-label="Skipped question pages"');
$rangeAnnouncements = substr_count($html, 'Showing 21–21 of 21 skipped questions · Page 2 of 2');

if ($paginationLandmarks !== 1 || $rangeAnnouncements !== 1) {
    fwrite(STDERR, sprintf(
        "FAIL: expected one skipped-question pagination landmark and one range announcement; got %d and %d\n",
        $paginationLandmarks,
        $rangeAnnouncements
    ));
    exit(1);
}

if (!str_contains($html, 'skipped_page=1') || !str_contains($html, 'Question synthetic-question')) {
    fwrite(STDERR, "FAIL: the previous-page action or current skipped item is missing\n");
    exit(1);
}

echo "PASS: multi-page skipped-question navigation renders once with a single range announcement.\n";
