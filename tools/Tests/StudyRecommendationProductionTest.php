<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . "/app/Core/Autoloader.php";
\App\Core\Autoloader::register();

use App\Controllers\StudyDashboardController;

$data = StudyDashboardController::data();
$recommendations = $data["recommendations"] ?? [];

if (empty($recommendations)) {
    fwrite(STDERR, "[FAIL] production recommendations exist\n");
    exit(1);
}

echo "[PASS] production recommendations exist\n";

foreach ($recommendations as $recommendation) {
    if (
        !isset(
            $recommendation["title"],
            $recommendation["reason"],
            $recommendation["action"],
            $recommendation["label"]
        )
        || !str_starts_with(
            (string) $recommendation["action"],
            "/quiz?"
        )
    ) {
        fwrite(STDERR, "[FAIL] production recommendation action\n");
        exit(1);
    }
}

echo "[PASS] production recommendations are actionable\n";
echo "[PASS] Production recommendation contract verified.\n";
