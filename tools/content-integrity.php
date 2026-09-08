<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

$arguments = array_slice($argv, 1);
foreach ($arguments as $argument) {
    if ($argument !== '--json') {
        fwrite(STDERR, "Unknown content-integrity option: {$argument}\n");
        exit(2);
    }
}

$report = \App\Services\Shared\ContentIntegrityService::analyzeRepository();
if (in_array('--json', $arguments, true)) {
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), "\n";
    exit($report['portfolio']['status'] === 'FAIL' ? 1 : 0);
}

$portfolio = $report['portfolio'];
printf(
    "PORTFOLIO_STATUS=%s QUESTIONS=%d ELIGIBLE=%d/%d LINKED=%d FLASHCARDS=%d JUSTIFIED_EXCLUSIONS=%d ACCIDENTAL_NON_DERIVABLE=%d\n",
    $portfolio['status'],
    $portfolio['total_canonical'],
    $portfolio['total_eligible'],
    $portfolio['total_canonical'],
    $portfolio['total_linked'],
    $portfolio['total_derivable_cards'],
    $portfolio['total_justified_exclusions'],
    $portfolio['total_accidental_non_derivable']
);
foreach ($report['boards'] as $board) {
    printf(
        "BOARD=%s QUESTIONS=%d ELIGIBLE=%d/%d LINKS=%d CARDS=%d STATUS=%s\n",
        $board['board_id'],
        $board['canonical_owned'],
        $board['owned_eligible'],
        $board['canonical_owned'],
        $board['linked_questions'],
        $board['derivable_flashcards'],
        $board['readiness_status']
    );
}
if (($report['semantic_review']['standalone_study_materials'] ?? []) !== []) {
    echo 'SEMANTIC_REVIEW_STANDALONE_MATERIALS=', implode(',', $report['semantic_review']['standalone_study_materials']), "\n";
}
