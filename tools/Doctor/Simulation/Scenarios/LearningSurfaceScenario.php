<?php

declare(strict_types=1);

namespace Tools\Doctor\Simulation\Scenarios;

use Tools\Doctor\Simulation\ApplicationSimulator;
use Tools\Doctor\Simulation\SimulationScenario;

final class LearningSurfaceScenario extends SimulationScenario
{
    public function name(): string
    {
        return 'Learning surfaces';
    }

    public function run(
        ApplicationSimulator $simulation
    ): void {
        /*
         * 1. Developer dashboard
         *
         * Verifies that the dashboard can build its
         * repository-health report and render normally.
         */
        $simulation
            ->get('/dashboard')
            ->execute()
            ->assertSuccessful()
            ->assertContains('Developer Dashboard');

        /*
         * 2. Learning profile
         *
         * Verifies that learning history, analytics,
         * weaknesses, recommendations and coaching
         * can be assembled into the profile page.
         */
        $simulation
            ->get('/profile')
            ->execute()
            ->assertSuccessful()
            ->assertContains('Learning Profile')
            ->assertContains('Current Level')
            ->assertContains('Performance')
            ->assertContains('Coach');

        /*
         * 3. Progress page
         *
         * Verifies that persisted learning statistics
         * can be rendered independently.
         */
        $simulation
            ->get('/progress')
            ->execute()
            ->assertSuccessful()
            ->assertContains('My Progress')
            ->assertContains('Overall Average')
            ->assertContains('Total Quizzes')
            ->assertContains('Recent Attempts');
    }
}
