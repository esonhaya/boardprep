<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . "/app/Core/Autoloader.php";
\App\Core\Autoloader::register();

$board = ["subjects" => [
    ["subject" => "English", "percentage" => 100],
]];
$subjects = ["English" => [
    "domains" => [["domain" => "Grammar", "percentage" => 100]],
    "difficulty" => ["easy" => 100],
]];

$requests = BlueprintRequestPlanBuilder::build($board, $subjects, 5);

assert(count($requests) === 1);
assert($requests[0] instanceof SelectionRequest);
assert($requests[0]->subject === "English");
assert($requests[0]->domain === "Grammar");
assert($requests[0]->questionCount === 5);

echo "[PASS] Blueprint request plan builder assertions verified.\n";
