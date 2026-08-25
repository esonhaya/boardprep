<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

$result = \AdaptivePriorityBuilder::build(
    [
        ["id" => 1, "topic" => "Grammar"],
        ["id" => 2, "topic" => "Vocabulary"],
    ],
    [
        "Grammar" => ["correct" => 0, "wrong" => 3],
    ]
);

assert(count($result) === 2);
assert($result[0]["id"] === 1);
assert($result[1]["id"] === 2);

echo "[PASS] Adaptive priority builder assertions verified.\n";
