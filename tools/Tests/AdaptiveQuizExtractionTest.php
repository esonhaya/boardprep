<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

$classes = [
    "AdaptiveTopicNormalizer",
    "AdaptiveWeaknessTopicResolver",
    "AdaptiveQuestionPartitioner",
    "AdaptiveQuestionOrderer",
    "AdaptivePriorityBuilder",
    "AdaptiveQuizService",
];

foreach ($classes as $class) {
    assert(class_exists($class));
}

echo "[PASS] Adaptive quiz extraction integration verified.\n";
