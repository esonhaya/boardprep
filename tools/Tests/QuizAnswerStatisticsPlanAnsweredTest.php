<?php

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
App\Core\Autoloader::register();

$questions = [
    [
        'id' => 'q1',
        'choices' => [
            'One',
            'Two',
            'Three',
            'Four',
        ],
        'answer' => 'One',
    ],
];

$result = App\Services\Quiz\Result\QuizAnswerStatisticsPlan::build(
    $questions,
    ['q1' => 'A']
);

if (
    count($result) !== 1
    || $result[0]['question_id'] !== 'q1'
    || $result[0]['correct'] !== true
) {
    throw new RuntimeException('answered plan failed');
}

echo "[PASS] Statistics plan evaluates answered questions through production scoring.\n";
