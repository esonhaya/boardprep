<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . "/app/Core/Autoloader.php";
\App\Core\Autoloader::register();

\BlueprintDistributionGuard::assertValid([
    ["subject" => "English", "questionCount" => 5],
]);

$threw = false;
try {
    \BlueprintDistributionGuard::assertValid([
        ["subject" => "", "questionCount" => 5],
    ]);
} catch (\InvalidArgumentException $exception) {
    $threw = true;
}
assert($threw);

echo "[PASS] Blueprint distribution guard assertions verified.\n";
