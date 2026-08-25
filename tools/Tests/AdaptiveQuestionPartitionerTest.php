<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

$parts = \AdaptiveQuestionPartitioner::partition(
    [
        ["id" => 1, "topic" => "Grammar"],
        ["id" => 2, "topic" => "Vocabulary"],
        ["id" => 3, "topic" => " grammar "],
    ],
    ["grammar"]
);

assert(array_column($parts["priority"], "id") === [1, 3]);
assert(array_column($parts["normal"], "id") === [2]);

echo "[PASS] Adaptive question partitioner assertions verified.\n";
