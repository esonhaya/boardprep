<?php

declare(strict_types=1);

namespace Tools\Doctor\Project\BoardPrep\Simulation;

use Tools\Doctor\Project\BoardPrep\Simulation\Output\SimulationReport;

final class SimulationCommand
{
    public static function run(): int
    {
        SimulationStorageFixture::activate(dirname(__DIR__, 5));
        $suite = SimulationSuite::run();

        echo SimulationReport::render(
            $suite['results'],
            $suite['summary']
        );

        echo PHP_EOL;

        $exitCode = $suite['summary']['success'] ? 0 : 1;
        if ($exitCode !== 0) {
            $_ENV['BOARDPREP_KEEP_SIMULATION_FIXTURES'] = '1';
            putenv('BOARDPREP_KEEP_SIMULATION_FIXTURES=1');
        }

        return $exitCode;
    }
}
