<?php

declare(strict_types=1);

namespace Tools\Tests\Simulation;

use Tools\Doctor\Simulation\ApplicationSimulator;
use Tools\Doctor\Simulation\SimulationResponse;

final class SimulationKernelTest
{
    public function run(): void
    {
        $simulation = new ApplicationSimulator();

        $simulation
            ->get('/')
            ->response(
                new SimulationResponse(
                    status: 200,
                    body: '<h1>BoardPrep</h1>'
                )
            )
            ->assertStatus(200)
            ->assertSuccessful()
            ->assertContains('BoardPrep')
            ->assertNotContains('Fatal error');

        if (!$simulation->passed()) {
            throw new \RuntimeException(
                implode(
                    "\n",
                    $simulation->result()->failures()
                )
            );
        }

        echo "PASS: Simulation kernel\n";
        echo "Steps: {$simulation->result()->passCount()}\n";
    }
}
