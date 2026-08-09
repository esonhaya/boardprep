<?php

declare(strict_types=1);

namespace Tools\Doctor\Simulation;

use Tools\Doctor\Simulation\Registry\DefaultScenarioRegistry;
use Tools\Doctor\Simulation\Runner\SimulationRunner;

final class SimulationSuite
{
    public static function run(): array
    {
        $runner = new SimulationRunner(
            DefaultScenarioRegistry::create()
        );

        $results = $runner->run();

        return [
            'results' => $results,
            'summary' => $runner->summarize($results),
        ];
    }
}
