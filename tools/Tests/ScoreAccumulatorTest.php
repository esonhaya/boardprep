<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . "/app/Core/Autoloader.php";
\App\Core\Autoloader::register();

$accumulator = new ScoreAccumulator();
$accumulator->record(true, true);
$accumulator->record(false, true);
$accumulator->record(false, false);

$result = $accumulator->summarize([["id" => 1], ["id" => 2], ["id" => 3]]);

assert($result["correct"] === 1);
assert($result["incorrect"] === 1);
assert($result["unanswered"] === 1);
assert($result["percentage"] === 33 || $result["percentage"] === 34);

echo "[PASS] ScoreAccumulator assertions verified.\n";
