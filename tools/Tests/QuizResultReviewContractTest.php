<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . "/app/Core/Autoloader.php";
\App\Core\Autoloader::register();

$_SESSION = [];
\SessionService::set('questions', [[
    'id' => 'review-1',
    'question' => 'Which answer is correct?',
    'choices' => ['Wrong', 'Right'],
    'answer' => 'Right',
    'explanation' => 'The second choice is correct.',
]]);
\SessionService::set('answers', ['review-1' => 'B']);
\SessionService::set('quiz_session', ['id' => 'completed-review']);
\SessionService::set('attempt_persisted', true);

$result = \QuizResultService::build();
$review = $result['review'][0] ?? [];
if (!is_array($review['question'] ?? null)
    || ($review['question']['question'] ?? '') !== 'Which answer is correct?'
    || ($review['userAnswer'] ?? '') !== 'Right'
    || ($review['question']['answer'] ?? '') !== 'Right'
    || ($review['question']['explanation'] ?? '') !== 'The second choice is correct.'
) {
    throw new RuntimeException('completed result review contract is incomplete');
}

$summary = $result['summary'];
$review = $result['review'];
$actions = [];
ob_start();
include dirname(__DIR__, 2) . "/app/Views/quiz/result.php";
$html = (string) ob_get_clean();

foreach ([
    'Which answer is correct?',
    'Right',
    'The second choice is correct.',
] as $text) {
    if (!str_contains($html, $text)) {
        throw new RuntimeException('result review did not render: ' . $text);
    }
}

$_SESSION = [];
echo "[PASS] completed result review preserves and renders question context.\n";
