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
            ->assertSuccessful()
            ->assertNotContains(
                'Fatal error'
            );

        $simulation
            ->get('/let')
            ->execute()
            ->assertSuccessful()
            ->assertContains(
                'LET'
            );

        $simulation
            ->get('/english')
            ->execute()
            ->assertSuccessful()
            ->assertContains(
                'English'
            );
    }
}
