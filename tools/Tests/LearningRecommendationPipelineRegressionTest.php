<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . "/app/Core/Autoloader.php";
\App\Core\Autoloader::register();

use App\Services\Learning\RecommendationService;
use App\Services\Learning\StudyDashboardService;
use App\Services\Learning\WeaknessService;
use App\Services\Learning\WeaknessStorageService;
use App\Services\Profile\LearningProfileService;

$original = WeaknessStorageService::all();

try {
    WeaknessService::clear();
    WeaknessService::analyze([
        ["topic" => "Grammar", "correct" => false],
        ["topic" => "Grammar", "correct" => true],
        ["topic" => "Reading", "correct" => false],
    ]);
    WeaknessService::analyze([
        ["topic" => "Grammar", "correct" => false],
        ["topic" => "Reading", "correct" => false],
    ]);

    $weaknesses = WeaknessService::all();
    $recommendations = RecommendationService::generate(
        ["totalQuizzes" => 2, "averageScore" => 50],
        array_merge($weaknesses, [["topic" => "grammar", "accuracy" => 10], "legacy"])
    );
    $profile = LearningProfileService::build([], $weaknesses);

    $attempts = [
        ["subject" => "English", "topic" => "Grammar", "score" => 1, "total" => 5, "percentage" => 20],
        ["subject" => "English", "topic" => "Grammar", "score" => 3, "total" => 5, "percentage" => 60],
        ["subject" => "English", "topic" => "Reading", "score" => 4, "total" => 5, "percentage" => 80],
    ];
    $dashboard = StudyDashboardService::build($attempts);
    $action = (string) ($dashboard["studyPlan"][0]["action"] ?? "");
    parse_str((string) parse_url($action, PHP_URL_QUERY), $parameters);

    $checks = [
        "multi-attempt weakness totals accumulate" =>
            ($weaknesses["Grammar"]["correct"] ?? null) === 1
            && ($weaknesses["Grammar"]["wrong"] ?? null) === 2,
        "stored weakness exposes derivation fields" =>
            ($weaknesses["Grammar"]["topic"] ?? null) === "Grammar"
            && ($weaknesses["Grammar"]["accuracy"] ?? null) === 33,
        "recommendations prioritize and deduplicate weakest topics" =>
            array_values(array_filter($recommendations, static fn(string $item): bool => str_starts_with($item, "Review:")))
                === ["Review: Reading", "Review: Grammar"],
        "profile counts only concrete weaknesses" => ($profile["weaknessCount"] ?? null) === 2,
        "dashboard averages repeated topic attempts" =>
            ($dashboard["weakestTopics"][0]["topic"] ?? null) === "Grammar"
            && ($dashboard["weakestTopics"][0]["average"] ?? null) === 40,
        "study plan keeps complete actionable quiz parameters" =>
            ($parameters["action"] ?? null) === "start"
            && ($parameters["subject"] ?? null) === "English"
            && ($parameters["topic"] ?? null) === "Grammar"
            && ($parameters["mode"] ?? null) === "practice"
            && ($parameters["count"] ?? null) === "5"
            && ($parameters["difficulty"] ?? null) === "mixed",
        "empty learner remains actionable without false performance advice" =>
            RecommendationService::generate(["totalQuizzes" => 0, "averageScore" => 0], [])
                === ["Keep taking quizzes to build your learning profile."]
            && (StudyDashboardService::build([])["studyPlan"][0]["topic"] ?? null) === "General",
    ];

    foreach ($checks as $label => $passed) {
        if (!$passed) {
            throw new RuntimeException($label);
        }
        echo "[PASS] {$label}\n";
    }
} finally {
    WeaknessService::clear();
    WeaknessStorageService::save($original);
}

echo "[PASS] learning recommendation pipeline regressions verified.\n";
