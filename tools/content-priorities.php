<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Core/Autoloader.php';

\App\Core\Autoloader::register();

$arguments = array_slice($argv, 1);
foreach ($arguments as $argument) {
    if ($argument !== '--json') {
        fwrite(STDERR, "Unknown content-priorities option: {$argument}\nUsage: php tools/content-priorities.php [--json]\n");
        exit(2);
    }
}

$report = \App\Services\Shared\ContentPriorityService::analyzeRepository();
if (in_array('--json', $arguments, true)) {
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), PHP_EOL;
    exit(($report['portfolio']['integrity_status'] ?? '') === 'FAIL' ? 1 : 0);
}

$portfolio = $report['portfolio'];
$usage = $portfolio['learner_usage'];
printf(
    "PORTFOLIO_STATUS=%s QUESTIONS=%d ELIGIBLE=%d LINKED=%d RESOURCES=%d CARDS=%d\n",
    $portfolio['integrity_status'],
    $portfolio['question_count'],
    $portfolio['eligible_question_count'],
    $portfolio['linked_question_count'],
    $portfolio['study_resource_count'],
    $portfolio['flashcard_count']
);
printf(
    "USAGE questions_with_usage=%d never_seen=%d times_used=%d accuracy=%s\n",
    $usage['questions_with_usage'],
    $usage['questions_never_seen'],
    $usage['times_used'],
    $usage['accuracy'] === null ? 'INSUFFICIENT_DATA' : $usage['accuracy'] . '%'
);
foreach ($report['priorities'] as $priority) {
    printf(
        "PRIORITY=%s TARGET=%s LEVEL=%s REASONS=%s ACTION=%s\n",
        $priority['priority'],
        $priority['target'],
        $priority['level'],
        implode(',', $priority['reason_codes']),
        $priority['recommended_action']
    );
}
printf("RUNTIME_MS=%s\n", $report['runtime']['total_runtime_ms']);
