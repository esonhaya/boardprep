<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . "/app/Core/Autoloader.php";
\App\Core\Autoloader::register();

$_SESSION = [
    "questions" => [["id" => 1]],
    "answers" => [1 => "A"],
    "quiz_session" => ["id" => "session-1"],
];

$input = QuizResultSessionReader::read();

assert($input["questions"] === [["id" => 1]]);
assert($input["answers"] === [1 => "A"]);
assert($input["session"]["id"] === "session-1");

echo "[PASS] QuizResultSessionReader assertions verified.\n";
