<?php

declare(strict_types=1);

$routes = file_get_contents(
    dirname(__DIR__, 2) . "/routes/web.php"
);

if ($routes === false) {
    fwrite(STDERR, "[FAIL] Cannot read production routes.\n");
    exit(1);
}

if (strpos($routes, '"/study"') === false) {
    fwrite(STDERR, "[FAIL] /study route missing.\n");
    exit(1);
}

if (strpos($routes, 'StudyDashboardController::class') === false) {
    fwrite(STDERR, "[FAIL] StudyDashboardController route binding missing.\n");
    exit(1);
}

echo "[PASS] /study production route registered.\n";
