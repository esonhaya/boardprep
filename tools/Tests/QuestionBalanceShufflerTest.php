<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

$groups = \QuestionBalanceShuffler::shuffleGroups([
    "grammar" => [["id" => 1], ["id" => 2]],
    "vocabulary" => [["id" => 3]],
]);

assert(count($groups) === 2);
assert(count($groups["grammar"]) === 2);
assert($groups["vocabulary"][0]["id"] === 3);

echo "[PASS] Question balance shuffler assertions verified.\n";
