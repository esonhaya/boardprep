<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

assert(\QuestionBalanceDifficultyResolver::resolve([]) === "mixed");
assert(\QuestionBalanceDifficultyResolver::resolve(["difficulty" => " EASY "]) === " easy ");

echo "[PASS] Question balance difficulty resolver assertions verified.\n";
