<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . "/app/Core/Autoloader.php";
\App\Core\Autoloader::register();

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
    ["score" => 4, "total" => 5, "percentage" => 80, "results" => []]
);

assert(str_starts_with($attempt["id"], "attempt-"));
assert($attempt["session_id"] === "session-395");
assert($attempt["user_id"] === "session:session-395");
assert($attempt["question_count"] === 5);
assert($attempt["score"] === 4);
assert($attempt["percentage"] === 80);
assert($attempt["completed"] === true);

echo "[PASS] QuizResultAttemptFactory assertions verified.\n";
