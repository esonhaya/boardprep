<?php

declare(strict_types=1);

namespace Tools\Tests\Simulation;

use Tools\Doctor\Project\BoardPrep\Simulation\ApplicationSimulator;

final class SimulationKernelTest
{
    public function run(): void
    {
        $simulation = new ApplicationSimulator();

        $simulation
            ->get('/')
            ->execute()
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

        echo "PASS: Application simulation\n";
        echo "Steps: {$simulation->result()->passCount()}\n";
    }
}
