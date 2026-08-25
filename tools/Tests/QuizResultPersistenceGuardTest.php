<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . "/app/Core/Autoloader.php";
\App\Core\Autoloader::register();

assert(
    QuizResultPersistenceGuard::shouldPersist(
        ["id" => "session-1"],
        false
    ) === true
);

assert(
    QuizResultPersistenceGuard::shouldPersist(
        ["id" => "session-1"],
        true
    ) === false
);

assert(
    QuizResultPersistenceGuard::shouldPersist(
        [],
        false
    ) === false
);

echo "[PASS] QuizResultPersistenceGuard assertions verified.\n";
