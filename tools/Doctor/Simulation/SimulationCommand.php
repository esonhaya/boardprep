<?php

declare(strict_types=1);

namespace Tools\Doctor\Simulation;

use Tools\Doctor\Simulation\Output\SimulationReport;

final class SimulationCommand
{
    public static function run(): int
    {
        $suite = SimulationSuite::run();

        echo SimulationReport::render(
            $suite['results'],
            $suite['summary']
        );

        echo PHP_EOL;

        return $suite['summary']['success']
            ? 0
            : 1;
    }
}
