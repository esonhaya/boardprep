<?php

declare(strict_types=1);

namespace Tools\Doctor\Simulation;

abstract class SimulationScenario
{
    abstract public function name(): string;

    abstract public function run(
        ApplicationSimulator $simulation
    ): void;
}
