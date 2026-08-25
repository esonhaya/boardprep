<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . "/app/Core/Autoloader.php";
\App\Core\Autoloader::register();

$_SESSION = [
    "questions" => [
        [
            "id" => 1,
            "question" => "Capital of France?",
            "choices" => ["London", "Paris", "Rome", "Oslo"],
            "answer" => "Paris",
        ],
        [
            "id" => 2,
            "question" => "Capital of Italy?",
            "choices" => ["Rome", "Paris", "Oslo", "Berlin"],
            "answer" => "Rome",
        ],
    ],
    "answers" => [
        1 => "B",
        2 => "Paris",
    ],
];

$result = QuizResultService::build();

assert(isset($result["summary"]));
assert(isset($result["review"]));
assert($result["summary"]["score"] === 1);
assert($result["summary"]["total"] === 2);
assert($result["summary"]["percentage"] === 50);
assert(count($result["review"]) === 2);

echo "[PASS] QuizResult extraction integration verified.\n";
