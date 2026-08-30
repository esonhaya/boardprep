<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . "/app/Core/Autoloader.php";
\App\Core\Autoloader::register();

$summary = [
    'totalAttempts' => 1,
    'completedAttempts' => 1,
    'averageScore' => 40,
    'bestScore' => 40,
];
$recommendations = [[
    'title' => 'Review Grammar',
    'reason' => 'Current average: 40%',
    'action' => '/quiz?action=start&subject=English&topic=Grammar&mode=practice&count=5&difficulty=mixed',
    'label' => 'Practice Grammar',
]];

ob_start();
include dirname(__DIR__, 2) . '/app/Views/progress/index.php';
$html = (string) ob_get_clean();

if (!str_contains($html, 'Review Grammar')
    || !str_contains($html, 'Practice Grammar')
    || !str_contains($html, 'topic=Grammar')
    || !str_contains($html, 'href="/quiz?action=start')) {
    throw new RuntimeException('progress recommendations are not rendered as usable study actions');
}

echo "[PASS] progress recommendations render canonical quiz actions.\n";
