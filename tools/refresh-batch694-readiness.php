#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();
$root = dirname(__DIR__);
$questions = json_decode((string) file_get_contents($root . '/storage/questions.json'), true, 512, JSON_THROW_ON_ERROR);
$native = ['let'=>[],'psychometrician'=>[]];
foreach ($questions as $question) {
    if (!is_array($question) || ($question['status'] ?? 'active') !== 'active') continue;
    $tax = (array) ($question['taxonomy'] ?? []);
    $board = (string) ($tax['board_id'] ?? $question['board'] ?? '');
    $concept = (string) ($tax['concept_id'] ?? '');
    if (isset($native[$board]) && $concept !== '') $native[$board][$concept] = ($native[$board][$concept] ?? 0) + 1;
}
$targets = [
    'let'=>['values-character-and-habits','values-civic-responsibility','values-clarification','values-diversity-and-dignity','values-ethical-reasoning','values-reflective-practice','values-respectful-dialogue'],
    'psychometrician'=>['psych-development-theories','psych-development-research-ethics','psych-development-nature-nurture'],
];
foreach ($targets as $board=>$leafIds) {
    $path = $root . '/storage/source-readiness-' . $board . '.json';
    $records = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    foreach ($records as &$record) {
        $leafId = (string) ($record['leaf_id'] ?? '');
        if (!in_array($leafId, $leafIds, true)) continue;
        $count = (int) ($native[$board][$leafId] ?? 0);
        $record['current_question_count'] = $count;
        $record['deficit_to_5'] = max(0, 5 - $count);
        $record['deficit_to_10'] = max(0, 10 - $count);
        $record['deficit_to_20'] = max(0, 20 - $count);
        if ($board === 'let' || $leafId === 'psych-development-theories') {
            $record['readiness'] = 'REVIEW';
            $record['review_reason'] = 'Batch 694 added the bounded source-linked cohort. Further expansion requires independent evidence-depth review; item-level academic approval remains pending.';
            $record['max_new_questions'] = 0;
            $record['planned_new_questions'] = 0;
        }
        $record['limitations'][] = 'Batch 694 content is source-linked and structurally checked; independent academic review and owner approval remain pending.';
        $record['limitations'] = array_values(array_unique($record['limitations']));
        $record['evidence_hash'] = \App\Services\RepositoryHealth\Readiness\SourceReadinessRecord::evidenceHash($record);
    }
    unset($record);
    $temporary = $path . '.batch694-tmp';
    file_put_contents($temporary, json_encode($records, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n", LOCK_EX);
    if (!rename($temporary, $path)) throw new RuntimeException('Unable to update ' . basename($path));
    foreach ($records as $record) if (in_array((string) ($record['leaf_id'] ?? ''), $leafIds, true)) printf("%s %s %d %s\n", $board, $record['leaf_id'], $record['current_question_count'], $record['readiness']);
}
