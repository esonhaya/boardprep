<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

$result = \AdaptiveQuestionOrderer::merge(
    [["id" => 1], ["id" => 2]],
    [["id" => 3]]
);

assert(count($result) === 3);
assert(in_array(["id" => 1], $result, true));
assert(in_array(["id" => 2], $result, true));
assert(in_array(["id" => 3], $result, true));

echo "[PASS] Adaptive question orderer assertions verified.\n";
