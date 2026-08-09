<?php

declare(strict_types=1);

namespace Tools\Doctor\Simulation\Scenarios;

use Tools\Doctor\Simulation\ApplicationSimulator;
use Tools\Doctor\Simulation\SimulationScenario;

final class HomePageScenario extends SimulationScenario
{
    public function name(): string
    {
        return 'Home page';
    }

    public function run(
        ApplicationSimulator $simulation
    ): void {
        $simulation
            ->get('/')
            ->execute()
            ->assertSuccessful()
            ->assertContains('BoardPrep');
    }
}
