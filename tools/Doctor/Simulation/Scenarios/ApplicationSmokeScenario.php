<?php

declare(strict_types=1);

namespace Tools\Doctor\Simulation\Scenarios;

use Tools\Doctor\Simulation\ApplicationSimulator;
use Tools\Doctor\Simulation\SimulationScenario;

final class ApplicationSmokeScenario extends SimulationScenario
{
    public function name(): string
    {
        return 'Application Smoke Test';
    }

    public function run(
        ApplicationSimulator $simulation
    ): void {
        $simulation
            ->get('/')
            ->execute()
            ->assertStatus(200)
            ->assertSuccessful()
            ->assertContains('BoardPrep')
            ->assertNotContains('Fatal error');

        $simulation
            ->get('/grammar')
            ->execute()
            ->assertStatus(200)
            ->assertSuccessful()
            ->assertContains('Grammar')
            ->assertNotContains('Fatal error');
    }
}
