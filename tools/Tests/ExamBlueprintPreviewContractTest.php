<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/Core/Autoloader.php';
\App\Core\Autoloader::register();

$expected = [
    'CE' => ['Principles of Structural Analysis and Design' => 35, 'Applied Mathematics, Surveying, Transportation and Construction' => 35, 'Hydraulics and Principles of Geotechnical Engineering' => 30],
    'ME' => ['Mathematics' => 35, 'Power Plant Engineering' => 35, 'Machine Design, Materials and Shop Practice' => 30],
    'REE' => ['Mathematics' => 25, 'Engineering Sciences and Allied Subjects' => 30, 'Electrical Engineering Professional Subjects' => 45],
    'ARCH' => ['History/Theory, Planning and Practice' => 30, 'Utilities, Structural Conceptualization, Materials/Technology' => 30, 'Architectural Design and Site Planning' => 40],
];

foreach ($expected as $boardCode => $expectedWeights) {
    $preview = ExamBlueprintPreviewService::forSetup($boardCode, '*', 80);
    if (($preview['status'] ?? '') !== 'REGISTERED_OFFICIAL') {
        throw new RuntimeException("{$boardCode}: official weighting was not described as registered.");
    }
    $weights = array_column($preview['lines'] ?? [], 'percentage', 'name');
    if (array_keys($weights) !== array_keys($expectedWeights)) {
        throw new RuntimeException("{$boardCode}: preview areas did not match the registered program.");
    }
    foreach ($expectedWeights as $name => $weight) {
        if (abs((float) ($weights[$name] ?? -1) - (float) $weight) > 0.001) {
            throw new RuntimeException("{$boardCode}/{$name}: setup preview changed a registered weight.");
        }
    }
    if (array_sum(array_column($preview['lines'], 'question_count')) !== 80) {
        throw new RuntimeException("{$boardCode}: setup preview count rounding did not total 80.");
    }
}

$architecture = ExamBlueprintPreviewService::forSetup('ARCH', '*', 20);
$areaA = array_values(array_filter($architecture['lines'], static fn(array $line): bool => ($line['name'] ?? '') === 'History/Theory, Planning and Practice'))[0] ?? null;
if (!is_array($areaA) || ($areaA['question_count'] ?? null) !== 6
    || array_column($areaA['subareas'] ?? [], 'question_count') !== [3, 3]) {
    throw new RuntimeException('Architecture Area A did not preserve its combined 30% quota and explicit internal split.');
}

$internal = ExamBlueprintPreviewService::forSetup('CSE', '*', 10);
if (($internal['status'] ?? '') !== 'INTERNAL_TARGETS'
    || array_sum(array_column($internal['lines'] ?? [], 'question_count')) !== 10) {
    throw new RuntimeException('CSE internal targets were presented as official or did not round to the requested count.');
}

$cpa = ExamBlueprintPreviewService::forSetup('CPA', '*', 20);
if (($cpa['status'] ?? '') !== 'WEIGHTING_UNAVAILABLE' || ($cpa['lines'] ?? []) !== []) {
    throw new RuntimeException('CPA received a fabricated full-board distribution.');
}

$focused = ExamBlueprintPreviewService::forSetup('ME', 'b644-me-subject-mathematics', 5);
if (($focused['status'] ?? '') !== 'FOCUSED_SUBJECT' || ($focused['lines'] ?? []) !== []) {
    throw new RuntimeException('Focused subject mode falsely displayed a full-board distribution.');
}

$view = file_get_contents(dirname(__DIR__, 2) . '/app/Views/quiz/settings.php');
$script = file_get_contents(dirname(__DIR__, 2) . '/public/assets/js/quiz-settings.js');
$styles = file_get_contents(dirname(__DIR__, 2) . '/public/assets/css/rich-product.css');
foreach ([
    [$view, 'data-exam-blueprint-preview'],
    [$view, 'data-exam-blueprint-focused-note'],
    [$view, 'JavaScript is needed for that live preview'],
    [$view, 'No official subarea weights are asserted.'],
    [$script, 'data-blueprint-subarea-weight'],
    [$script, 'focusedBlueprintNote.hidden = !examSimulation || !focusedSubject'],
    [$styles, '.exam-blueprint-preview-list{grid-template-columns:1fr}'],
] as [$content, $contract]) {
    if (!is_string($content) || !str_contains($content, $contract)) {
        throw new RuntimeException("Mobile exam blueprint preview contract is missing: {$contract}");
    }
}

echo "[PASS] Exam setup previews distinguish sourced/internal/unavailable weighting, preserve exact rounding, and remain mobile-aware.\n";
