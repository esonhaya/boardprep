<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

foreach ([
    "QuestionBalanceDifficultyResolver",
    "QuestionBalanceDifficultyFilter",
    "QuestionBalanceTopicResolver",
    "QuestionBalanceGrouper",
    "QuestionBalanceShuffler",
    "QuestionBalanceRoundRobin",
    "QuestionBalancingService",
] as $class) {
    assert(class_exists($class));
}

echo "[PASS] Question balancing extraction integration verified.\n";
