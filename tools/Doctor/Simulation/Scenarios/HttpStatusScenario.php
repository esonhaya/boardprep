<?php

declare(strict_types=1);

namespace Tools\Doctor\Simulation\Scenarios;

use Tools\Doctor\Simulation\ApplicationSimulator;
use Tools\Doctor\Simulation\SimulationScenario;

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
