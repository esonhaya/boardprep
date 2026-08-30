<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . "/app/Core/Autoloader.php";
\App\Core\Autoloader::register();

$_SESSION = [
    "questions" => [[
        "id" => 1,
        "question" => "Which option is correct?",
        "choices" => ["First", "Second"],
        "answer" => "First",
    ]],
    "answers" => [1 => "A"],
    "quiz_session" => ["id" => "session-1"],
];

$input = QuizResultSessionReader::read();

assert(count($input["questions"]) === 1);
assert($input["questions"][0]["id"] === 1);
assert($input["answers"] === [1 => "A"]);
assert($input["session"]["id"] === "session-1");

echo "[PASS] QuizResultSessionReader assertions verified.\n";
