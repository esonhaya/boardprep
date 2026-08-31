<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

use App\Services\Question\Query\QuestionQueryFilters;
use App\Services\Question\Query\QuestionQueryPipeline;

$questions = [
    ['id' => 'active-1', 'status' => 'active', 'question' => 'Active question'],
    ['id' => 'draft-1', 'status' => 'draft', 'question' => 'Draft question'],
    ['id' => 'archived-1', 'status' => 'archived', 'question' => 'Archived question'],
];

$filtered = QuestionQueryPipeline::apply(
    $questions,
    QuestionQueryFilters::from(['status' => 'draft'])
);

if (count($filtered) !== 1 || $filtered[0]['id'] !== 'draft-1') {
    throw new RuntimeException('status filter did not isolate draft content');
}

if (QuestionQueryPipeline::apply($questions, QuestionQueryFilters::from(['status' => 'missing'])) !== []) {
    throw new RuntimeException('unknown status filter should return an empty result');
}

echo "[PASS] question inventory status filtering is accurate and safe for empty results.\n";
