<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

$groups = \QuestionBalanceGrouper::groupByTopic([
    ["id" => 1, "topic" => "Grammar"],
    ["id" => 2, "topic" => "Vocabulary"],
    ["id" => 3, "topic" => "grammar"],
]);

assert(array_column($groups["grammar"], "id") === [1, 3]);
assert(array_column($groups["vocabulary"], "id") === [2]);

echo "[PASS] Question balance grouper assertions verified.\n";
