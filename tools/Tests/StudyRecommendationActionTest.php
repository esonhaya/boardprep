<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . "/app/Core/Autoloader.php";
\App\Core\Autoloader::register();

use App\Services\Learning\StudyRecommendationService;

$attempts = [
    [
        "subject" => "English",
        "topic" => "Grammar",
        "score" => 2,
        "total" => 5,
        "percentage" => 40,
    ],
];

$weakest = [
    [
        "topic" => "Grammar",
        "average" => 40,
    ],
];

$recommendations = StudyRecommendationService::build(
    $attempts,
    $weakest
);

$first = $recommendations[0] ?? [];

$checks = [
    "recommendation exists" =>
        count($recommendations) >= 1,

    "topic preserved" =>
        ($first["topic"] ?? "") === "Grammar",

    "subject present" =>
        ($first["subject"] ?? "") === "English",

    "action present" =>
        str_starts_with(
            (string) ($first["action"] ?? ""),
            "/quiz?"
        ),

    "action carries topic" =>
        str_contains(
            (string) ($first["action"] ?? ""),
            "topic=Grammar"
        ),

    "label present" =>
        ($first["label"] ?? "") === "Practice Grammar",

    "limit preserved" =>
        count($recommendations) <= 3,
];

foreach ($checks as $label => $passed) {
    if (!$passed) {
        fwrite(STDERR, "[FAIL] {$label}\n");
        exit(1);
    }

    echo "[PASS] {$label}\n";
}

echo "[PASS] Study recommendation actionability verified. Assertions: "
    . count($checks)
    . "\n";
