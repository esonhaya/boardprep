<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

assert(\QuestionBalanceTopicResolver::resolve(["topic" => " Grammar "]) === "grammar");
assert(\QuestionBalanceTopicResolver::resolve([]) === "__unknown__");

echo "[PASS] Question balance topic resolver assertions verified.\n";
