<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

$audit = \App\Services\Quality\QuestionDistractorAuditService::analyze(
    \App\Core\App::container()->get(\App\Repositories\QuestionRepository::class)->all()
);
echo json_encode([
    'report_type' => 'QUESTION_DISTRACTOR_QUALITY_AUDIT',
    'report_schema_version' => 1,
    'summary' => $audit['summary'],
    'findings' => $audit['findings'],
    'interpretation' => 'ERROR means a deterministic duplicate was found. REVIEW findings are heuristic cues for a human content reviewer and do not alter academic approval.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . PHP_EOL;
