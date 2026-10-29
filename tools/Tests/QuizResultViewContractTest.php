<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . "/app/Core/Autoloader.php";
\App\Core\Autoloader::register();

$summary = [
    "score" => 4,
    "total" => 10,
    "percentage" => 40,
    "answered" => 8,
    "incorrect" => 4,
    "unanswered" => 2,
];

$review = [];
$actions = [
    [
        "label" => "Practice this again",
        "reason" => "Your score shows this area needs more practice.",
        "url" => "/quiz?action=start&topic=Grammar",
    ],
    [
        "label" => "Back to Study Dashboard",
        "reason" => "Use your study plan and recommendations for the next step.",
        "url" => "/study",
    ],
    [
        "label" => "View Progress",
        "reason" => "See how this result changed your learning history.",
        "url" => "/progress",
    ],
];

ob_start();
include dirname(__DIR__, 2) . "/app/Views/quiz/result.php";
$html = (string) ob_get_clean();

$checks = [
    "result heading" => strpos($html, "Quiz Result") !== false,
    "score percentage is distinguished" => strpos($html, "Score percentage") !== false && strpos($html, "40%") !== false,
    "answer accuracy excludes unanswered questions" => strpos($html, "Question answer accuracy") !== false && strpos($html, "50%") !== false,
    "outcome counts are explicit" => strpos($html, "<dt>Correct</dt><dd>4</dd>") !== false
        && strpos($html, "<dt>Incorrect</dt><dd>4</dd>") !== false
        && strpos($html, "<dt>Unanswered</dt><dd>2</dd>") !== false,
    "next steps shown" => strpos($html, "What Next?") !== false,
    "practice action shown" => strpos($html, "Practice this again") !== false,
    "study action shown" => strpos($html, "/study") !== false,
    "progress action shown" => strpos($html, "/progress") !== false,
    "review section shown" => strpos($html, "Answer Review") !== false,
];

foreach ($checks as $label => $passed) {
    if (!$passed) {
        fwrite(STDERR, "[FAIL] {$label}\n");
        exit(1);
    }
    echo "[PASS] {$label}\n";
}

echo "[PASS] Quiz result view contract verified. Assertions: "
    . count($checks) . "\n";

$summary = ["score" => 0, "total" => 5, "percentage" => 0, "answered" => 0, "incorrect" => 0, "unanswered" => 5];
$reviewUnavailable = true;
ob_start();
include dirname(__DIR__, 2) . "/app/Views/quiz/result.php";
$emptyAnswerHtml = (string) ob_get_clean();
if (!str_contains($emptyAnswerHtml, '<h3>—</h3>') || !str_contains($emptyAnswerHtml, 'No answers were submitted.')
    || !str_contains($emptyAnswerHtml, '<dt>Unanswered</dt><dd>5</dd>')) {
    throw new RuntimeException('an all-unanswered attempt displayed a fabricated answer-accuracy rate.');
}
echo "[PASS] all-unanswered result shows no answer-accuracy percentage and preserves the unanswered count.\n";
