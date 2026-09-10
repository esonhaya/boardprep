<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Core/Autoloader.php';

\App\Core\Autoloader::register();

$arguments = array_slice($argv, 1);
$filters = [];
foreach ($arguments as $argument) {
    if ($argument === '--json' || $argument === '--qa' || $argument === '--fix-queue') {
        continue;
    }
    if (preg_match('/^--(ids|board|subject|topic|severity|issue|detection|id-from|id-to)=(.+)$/', $argument, $match) !== 1) {
        fwrite(STDERR, "Unknown content-priorities option: {$argument}\nUsage: php tools/content-priorities.php [--json] [--qa|--fix-queue] [--ids=1,2] [--board=id] [--subject=id] [--topic=id] [--severity=ERROR] [--issue=CODE] [--detection=DETERMINISTIC] [--id-from=1] [--id-to=9]\n");
        exit(2);
    }
    $keys = ['ids' => 'question_ids', 'board' => 'board_id', 'subject' => 'subject_id', 'topic' => 'topic_id', 'severity' => 'severity', 'issue' => 'issue_code', 'detection' => 'detection_type', 'id-from' => 'id_from', 'id-to' => 'id_to'];
    $filters[$keys[$match[1]]] = $match[1] === 'ids' ? array_values(array_filter(explode(',', $match[2]))) : $match[2];
}

$report = \App\Services\Shared\ContentPriorityService::analyzeRepository($filters);
if (in_array('--json', $arguments, true)) {
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), PHP_EOL;
    exit(($report['portfolio']['integrity_status'] ?? '') === 'FAIL' ? 1 : 0);
}

if (in_array('--qa', $arguments, true) || in_array('--fix-queue', $arguments, true)) {
    $qa = $report['qa'] ?? [];
    $rows = in_array('--fix-queue', $arguments, true) ? ($qa['actionable_fix_queue'] ?? []) : ($qa['issues'] ?? []);
    printf("QA errors=%d warnings=%d review=%d info=%d total=%d\n", (int)($qa['summary']['ERROR'] ?? 0), (int)($qa['summary']['WARNING'] ?? 0), (int)($qa['summary']['REVIEW'] ?? 0), (int)($qa['summary']['INFO'] ?? 0), (int)($qa['summary']['total'] ?? 0));
    foreach ($rows as $issue) {
        printf("ISSUE=%s SEVERITY=%s IDS=%s ACTION=%s\n", $issue['issue_code'], $issue['severity'], implode(',', $issue['question_ids']), $issue['suggested_action']);
    }
    exit((int)($qa['summary']['ERROR'] ?? 0) > 0 ? 1 : 0);
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
