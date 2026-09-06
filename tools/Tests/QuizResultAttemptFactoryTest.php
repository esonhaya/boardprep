<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . "/app/Core/Autoloader.php";
\App\Core\Autoloader::register();

$_SESSION = [];

$session = [
    "id" => "session-395",
    "board" => "LET",
    "subject" => "English",
    "domain" => "Grammar",
    "mode" => "practice",
    "difficulty" => "mixed",
    "question_count" => 5,
    "question_ids" => [1, 2, 3, 4, 5],
    "started_at" => "2026-08-25T10:00:00+00:00",
];

$attempt = QuizResultAttemptFactory::create(
    $session,
    [["id" => 1], ["id" => 2]],
    [
        "score" => 4,
        "total" => 5,
        "percentage" => 80,
        "results" => [
            [
                "question" => ["id" => "q1"],
                "userAnswer" => "A",
                "correct" => true,
            ],
        ],
    ]
);

assert(str_starts_with($attempt["id"], "attempt-"));
assert($attempt["session_id"] === "session-395");
assert(str_starts_with($attempt["user_id"], "learner-"));
assert($attempt["user_id"] !== $attempt["session_id"]);
assert($attempt["question_count"] === 2);
assert($attempt["score"] === 4);
assert((float) $attempt["percentage"] === 80.0);
assert($attempt["completed"] === true);
assert(count($attempt["review"]) === 1);
assert($attempt["review"][0]["question"]["id"] === "q1");

echo "[PASS] QuizResultAttemptFactory assertions verified.\n";
