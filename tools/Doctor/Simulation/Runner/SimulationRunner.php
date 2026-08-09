<?php

declare(strict_types=1);

namespace Tools\Doctor\Simulation\Runner;

use Tools\Doctor\Simulation\ApplicationSimulator;
use Tools\Doctor\Simulation\Registry\ScenarioRegistry;
use Tools\Doctor\Simulation\SimulationResult;
use Tools\Doctor\Simulation\SimulationScenario;

final class SimulationRunner
{
    public function __construct(
        private readonly ScenarioRegistry $registry
    ) {
    }

    /**
     * @return array<int, array{
     *     scenario:string,
     *     result:SimulationResult
     * }>
     */
    public function run(): array
    {
        $results = [];

        foreach ($this->registry->all() as $scenarioClass) {
            /** @var SimulationScenario $scenario */
            $scenario = new $scenarioClass();

            $simulation = new ApplicationSimulator();

            try {
                $scenario->run($simulation);
            } catch (\Throwable $exception) {
                $simulation->result()->record(
                    'Scenario execution',
                    false,
                    get_class($exception)
                    . ': '
                    . $exception->getMessage()
                );
            }

            $results[] = [
                'scenario' => $scenario->name(),
                'result' => $simulation->result(),
            ];
        }

        return $results;
    }

    /**
     * @param array<int, array{
     *     scenario:string,
     *     result:SimulationResult
     * }> $results
     */
    public function summarize(array $results): array
    {
        $passed = 0;
        $failed = 0;
        $steps = 0;
        $failedSteps = 0;

        foreach ($results as $item) {
            $result = $item['result'];

            $steps += count($result->steps());
            $failedSteps += $result->failCount();

            if ($result->passed()) {
                $passed++;
            } else {
                $failed++;
            }
        }

        return [
            'scenarios' => count($results),
            'passed' => $passed,
            'failed' => $failed,
            'steps' => $steps,
            'failedSteps' => $failedSteps,
            'success' => $failed === 0,
        ];
    }
}
