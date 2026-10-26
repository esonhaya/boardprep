<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();
require_once dirname(__DIR__, 2) . '/bootstrap/app.php';

use App\Core\App;
use App\Services\Question\QuestionEligibilityService;
use App\Services\Quiz\Start\QuizStartPreparationService;
use App\Services\Learning\SubjectPerformanceService;

$questions = App::storage()->all('questions');
$sharedQuestion = null;
foreach ($questions as $question) {
    if (is_array($question) && (string) ($question['id'] ?? '') === '1') {
        $sharedQuestion = $question;
        break;
    }
}
if (!is_array($sharedQuestion)) {
    throw new RuntimeException('Canonical cross-board question fixture Q1 is unavailable.');
}

$eligibility = QuestionEligibilityService::index($questions);
$prepared = QuizStartPreparationService::prepare([
    'exam' => 'CSE',
    'subject' => 'verbal-ability',
    'count' => 1,
    'difficulty' => (string) ($sharedQuestion['difficulty'] ?? 'mixed'),
    'civil_service_level' => 'PROFESSIONAL',
], [$sharedQuestion], $eligibility);
$attemptContext = $prepared->questions[0]['attempt_context'] ?? [];
if (count($prepared->questions) !== 1
    || ($attemptContext['board'] ?? '') !== 'civil-service'
    || ($attemptContext['subject'] ?? '') !== 'verbal-ability'
    || ($attemptContext['civil_service_level'] ?? '') !== 'PROFESSIONAL') {
    throw new RuntimeException('The session snapshot did not retain its board-resolved CSE subject placement.');
}
$questionSnapshot = $prepared->questions[0];
$attempt = \QuizResultAttemptFactory::create(
    ['id' => 'synthetic-session', 'board' => 'civil-service', 'subject' => '*', 'mode' => 'exam', 'civil_service_level' => 'PROFESSIONAL'],
    [$questionSnapshot],
    [
        'score' => 1, 'total' => 1, 'unanswered' => 0,
        'results' => [[
            'question' => $questionSnapshot,
            'answered' => true,
            'correct' => true,
            'userAnswer' => $questionSnapshot['answer'] ?? '',
            'correctAnswer' => $questionSnapshot['answer'] ?? '',
        ]],
    ]
);
$subjectRows = SubjectPerformanceService::summarize([$attempt]);
if (($attempt['review'][0]['question']['attempt_context']['subject'] ?? '') !== 'verbal-ability'
    || ($subjectRows[0]['subject'] ?? '') !== 'verbal-ability') {
    throw new RuntimeException('Eligibility placement was not retained in historical result analytics.');
}

echo "[PASS] Quiz preparation and attempt history preserve the board-resolved cross-board subject placement.\n";
