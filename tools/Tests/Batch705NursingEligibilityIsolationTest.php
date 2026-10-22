<?php

declare(strict_types=1);

putenv('APP_ENV=production');
putenv('APP_DEBUG=false');
putenv('DB_DRIVER=json');

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();
require dirname(__DIR__, 2) . '/bootstrap/app.php';

use App\Core\App;
use App\Services\Question\QuestionEligibilityService;

$questions = App::storage()->all('questions');
$index = QuestionEligibilityService::index();
$crossBoardScienceIds = array_map('strval', [31, 32, 33, 34, 35, 36, 37, 38, 39, 40, 106, 107, 108, 109, 110, 111, 112, 113, 114, 115]);
$questionById = [];
foreach ($questions as $question) {
    if (is_array($question)) $questionById[(string) ($question['id'] ?? '')] = $question;
}

$activeNursing = array_values(array_filter(
    $questions,
    static fn(array $question): bool => ($question['status'] ?? '') === 'active'
        && QuestionEligibilityService::forExam($question, 'nursing', $index) !== null
));
if (count($activeNursing) !== 878) {
    throw new RuntimeException('Expected 878 active Nursing-eligible questions after isolating shared LET science; got ' . count($activeNursing));
}

foreach ($crossBoardScienceIds as $questionId) {
    $question = $questionById[$questionId] ?? null;
    if (!is_array($question) || ($question['taxonomy']['board_id'] ?? '') !== 'let') {
        throw new RuntimeException('Shared-science canonical LET question identity changed: ' . $questionId);
    }
    if (QuestionEligibilityService::forExam($question, 'nursing', $index) !== null) {
        throw new RuntimeException('LET shared-science question leaked into Nursing eligibility: ' . $questionId);
    }
    foreach (['let', 'civil-service'] as $boardId) {
        if (QuestionEligibilityService::forExam($question, $boardId, $index) === null) {
            throw new RuntimeException('Shared-science question lost preserved ' . $boardId . ' eligibility: ' . $questionId);
        }
    }
}

if (hash_file('sha256', dirname(__DIR__, 2) . '/storage/questions.json') !== '73cdc775cd7240b57898868d67902d833db1171f947c8dcc89081ddaf46fb4be') {
    throw new RuntimeException('Canonical question identities, stems, choices, answers, or explanations changed.');
}

echo "[PASS] Effective Nursing eligibility excludes 20 LET shared-science questions while preserving LET/Civil Service eligibility and canonical question bytes.\n";
