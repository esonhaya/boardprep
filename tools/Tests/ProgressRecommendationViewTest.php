<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . "/app/Core/Autoloader.php";
\App\Core\Autoloader::register();

$summary = [
    'totalAttempts' => 1,
    'completedAttempts' => 1,
    'averageScore' => 40,
    'bestScore' => 40,
    'answeredCount' => 8,
    'correctCount' => 6,
    'incorrectCount' => 2,
    'unansweredCount' => 3,
    'accuracy' => 75,
    'recentAccuracy' => 68,
    'recentAnsweredCount' => 25,
    'recentCorrectCount' => 17,
    'recentAttemptCount' => 5,
    'recentAttemptLimit' => 5,
    'recentAccuracyStatus' => 'AVAILABLE',
];
$recommendations = [[
    'title' => 'Review Grammar',
    'reason' => 'Current average: 40%',
    'action' => '/quiz?action=start&subject=English&topic=Grammar&mode=practice&count=5&difficulty=mixed',
    'label' => 'Practice Grammar',
]];

ob_start();
include dirname(__DIR__, 2) . '/app/Views/progress/index.php';
$html = (string) ob_get_clean();

if (!str_contains($html, 'Review Grammar')
    || !str_contains($html, 'Practice Grammar')
    || !str_contains($html, 'topic=Grammar')
    || !str_contains($html, 'href="/quiz?action=start')) {
    throw new RuntimeException('progress recommendations are not rendered as usable study actions');
}
$plain = preg_replace('/\s+/', ' ', strip_tags($html)) ?? '';
if (!preg_match('/Answer accuracy\s*75%/', $plain)
    || !preg_match('/Recent answer accuracy\s*68%/', $plain)
    || !str_contains($plain, '25 answer events across 5 recent quizzes')
    || !preg_match('/Average quiz score\s*40%/', $plain)
    || !preg_match('/Questions answered\s*8/', $plain)
    || !preg_match('/Correct answers\s*6/', $plain)
    || !preg_match('/Incorrect answers\s*2/', $plain)
    || !preg_match('/Unanswered questions\s*3/', $plain)) {
    throw new RuntimeException('progress page conflated pooled answer accuracy with average quiz score or omitted answer totals');
}

echo "[PASS] progress recommendations render canonical quiz actions.\n";
