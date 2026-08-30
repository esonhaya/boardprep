#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../app/Core/Autoloader.php';

\App\Core\Autoloader::register();

use Tools\Doctor\Project\BoardPrep\Simulation\Output\SimulationReport;
use Tools\Doctor\Project\BoardPrep\Simulation\Registry\DefaultScenarioRegistry;
use Tools\Doctor\Project\BoardPrep\Simulation\SimulationCommand;
use Tools\Doctor\Project\BoardPrep\Simulation\SimulationResult;

function requireContract(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$scenarioNames = [];
foreach (DefaultScenarioRegistry::create()->all() as $scenarioClass) {
    $scenarioNames[] = (new $scenarioClass())->name();
}

ob_start();
$exit = SimulationCommand::run();
$output = (string) ob_get_clean();

requireContract($exit === 0, 'The registered developer simulation did not exit successfully.');
requireContract(str_contains($output, 'BOARDPREP DEVELOPER SIMULATION'), 'Command report heading is missing.');
requireContract(str_contains($output, 'SCENARIOS=' . count($scenarioNames)), 'Scenario count does not match the registry.');
requireContract(str_contains($output, 'PASS=' . count($scenarioNames)), 'Pass count does not match executed scenarios.');
requireContract(str_contains($output, 'FAIL=0'), 'Successful command reported failures.');
requireContract(str_contains($output, 'SIMULATION_STATUS=PASS'), 'Successful command status is missing.');

$lastPosition = -1;
foreach ($scenarioNames as $scenarioName) {
    $position = strpos($output, $scenarioName);
    requireContract($position !== false, "Registered scenario is absent from report: {$scenarioName}");
    requireContract($position > $lastPosition, "Scenario order is not deterministic: {$scenarioName}");
    $lastPosition = $position;
}

$personas = [
    'NEW_LEARNER',
    'STRUGGLING_LEARNER',
    'IMPROVING_LEARNER',
    'STRONG_LEARNER',
    'MIXED_LEARNER',
    'EXAM_READY_LEARNER',
];
$lastPosition = -1;
foreach ($personas as $persona) {
    $position = strpos($output, $persona);
    requireContract($position !== false, "Executed learner persona is absent from report: {$persona}");
    requireContract($position > $lastPosition, "Persona order is not deterministic: {$persona}");
    requireContract(
        preg_match('/^' . preg_quote($persona, '/') . '\\s+PASS$/m', $output) === 1,
        "Executed learner persona did not report PASS: {$persona}"
    );
    $lastPosition = $position;
}

$failed = new SimulationResult();
$failed->record('STRUGGLING_LEARNER', false, 'STRUGGLING_LEARNER: expected targeted practice');
$failureOutput = SimulationReport::render(
    [['scenario' => 'Learner personas', 'result' => $failed]],
    ['scenarios' => 1, 'passed' => 0, 'failed' => 1, 'steps' => 1, 'failedSteps' => 1, 'success' => false]
);
requireContract(str_contains($failureOutput, 'Learner personas            FAIL'), 'Failed scenario status is missing.');
requireContract(str_contains($failureOutput, 'STRUGGLING_LEARNER          FAIL'), 'Failed persona status is missing.');
requireContract(
    str_contains($failureOutput, 'REASON: STRUGGLING_LEARNER: expected targeted practice'),
    'Useful persona failure reason is missing.'
);
requireContract(str_contains($failureOutput, 'SIMULATION_STATUS=FAIL'), 'Failure status is missing.');

echo "PASS: Developer simulation runner/report contract\n";
echo 'Scenarios: ' . count($scenarioNames) . "\n";
