<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . "/app/Core/Autoloader.php";
\App\Core\Autoloader::register();

use App\Services\Quiz\QuizResultActionService;

$session = [
    "topics" => ["Grammar"],
    "subject" => "English",
    "mode" => "practice",
    "difficulty" => "mixed",
    "question_count" => 10,
];

$actions = QuizResultActionService::build(
    $session,
    ["score" => 4, "total" => 10, "percentage" => 40]
);

$checks = [
    "three actions" => count($actions) === 3,
    "primary action present" => !empty($actions[0]["label"]),
    "primary action has reason" => !empty($actions[0]["reason"]),
    "primary action targets quiz" =>
        str_starts_with((string) ($actions[0]["url"] ?? ""), "/quiz?"),
    "topic carried" =>
        strpos((string) $actions[0]["url"], "topic=Grammar") !== false,
    "subject carried" =>
        strpos((string) $actions[0]["url"], "subject=English") !== false,
    "study dashboard action" => ($actions[1]["url"] ?? "") === "/study",
    "progress action" => ($actions[2]["url"] ?? "") === "/progress",
];

foreach ($checks as $label => $passed) {
    if (!$passed) {
        fwrite(STDERR, "[FAIL] {$label}\n");
        exit(1);
    }
    echo "[PASS] {$label}\n";
}

echo "[PASS] Quiz result action contract verified. Assertions: "
    . count($checks) . "\n";

$reviewActions = QuizResultActionService::build($session, ["score" => 4, "total" => 10, "percentage" => 40], [
    ["answered" => true, "correct" => false],
    ["answered" => false, "correct" => false],
]);
$mistakeActions = array_values(array_filter($reviewActions, static fn(array $action): bool => ($action['url'] ?? '') === '/mistake-notebook'));
if (count($mistakeActions) !== 1
    || ($mistakeActions[0]['label'] ?? '') !== 'Review mistakes and skipped questions'
    || !str_contains((string) ($mistakeActions[0]['reason'] ?? ''), 'eligible recovery action')) {
    throw new RuntimeException('A result with missed items did not offer a direct, evidence-based Mistake Notebook action.');
}
$correctOnlyActions = QuizResultActionService::build($session, ["score" => 2, "total" => 2, "percentage" => 100], [
    ["answered" => true, "correct" => true],
]);
if (count(array_filter($correctOnlyActions, static fn(array $action): bool => ($action['url'] ?? '') === '/mistake-notebook')) !== 0) {
    throw new RuntimeException('An all-correct result offered a misleading mistakes destination.');
}
echo "[PASS] Missed or skipped results link directly to the Mistake Notebook; all-correct results do not.\n";
