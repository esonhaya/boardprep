<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . "/app/Core/Autoloader.php";
\App\Core\Autoloader::register();

$requests = [
    new \SelectionRequest("English", "Grammar", [], 2),
    new \SelectionRequest("Science", "Biology", [], 2),
];

$result = \BlueprintAllocationReconciler::reconcile($requests, 5);

assert(count($result) === 2);
assert($result[0]->questionCount + $result[1]->questionCount === 5);

$result = \BlueprintAllocationReconciler::reconcile($result, 2);

assert($result[0]->questionCount + $result[1]->questionCount === 2);

echo "[PASS] Blueprint allocation reconciler production contract verified.\n";
