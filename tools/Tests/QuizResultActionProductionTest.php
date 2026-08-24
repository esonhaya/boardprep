<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . "/app/Core/Autoloader.php";
\App\Core\Autoloader::register();

use App\Services\Quiz\QuizResultActionService;

$actions = QuizResultActionService::build(
    [
        "topics" => ["Reading"],
        "subject" => "English",
        "mode" => "practice",
        "difficulty" => "easy",
        "question_count" => 5,
    ],
    [
        "score" => 5,
        "total" => 5,
        "percentage" => 100,
    ]
);

foreach ($actions as $index => $action) {
    if (
        empty($action["label"]) ||
        empty($action["reason"]) ||
        empty($action["url"])
    ) {
        fwrite(STDERR, "[FAIL] action {$index} incomplete\n");
        exit(1);
    }
    echo "[PASS] action {$index} complete\n";
}

if (
    ($actions[0]["url"] ?? "") !==
    "/quiz?action=start&subject=English&mode=practice&count=5&difficulty=easy&topic=Reading"
) {
    fwrite(STDERR, "[FAIL] production retake URL\n");
    exit(1);
}

echo "[PASS] production retake URL\n";
echo "[PASS] Quiz result action production contract verified.\n";
