<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

$result = \QuestionBalanceRoundRobin::balance([
    "grammar" => [["id" => 1], ["id" => 2]],
    "vocabulary" => [["id" => 3]],
]);

assert(array_column($result, "id") === [1, 3, 2]);

echo "[PASS] Question balance round-robin assertions verified.\n";
