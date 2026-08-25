<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

assert(\AdaptiveWeaknessTopicResolver::resolve([
    "Grammar" => ["correct" => 1, "wrong" => 3],
    "Reading" => ["correct" => 2, "wrong" => 0],
]) === ["grammar", "reading"]);

assert(\AdaptiveWeaknessTopicResolver::resolve([
    ["topic" => "Grammar"],
    ["topic" => " grammar "],
]) === ["grammar"]);

echo "[PASS] Adaptive weakness topic resolver assertions verified.\n";
