<?php

declare(strict_types=1);

namespace Tools\Doctor\Project\BoardPrep\Simulation\Scenarios;

use Tools\Doctor\Project\BoardPrep\Simulation\ApplicationSimulator;
use Tools\Doctor\Project\BoardPrep\Simulation\SimulationScenario;

final class HttpStatusScenario extends SimulationScenario
{
    public function name(): string
    {
        return 'HTTP status handling';
    }

    public function run(
        ApplicationSimulator $simulation
    ): void {
        $simulation
            ->get('/route-that-does-not-exist')
            ->execute()
            ->assertStatus(404)
            ->assertNotContains('Fatal error');
    }
}
