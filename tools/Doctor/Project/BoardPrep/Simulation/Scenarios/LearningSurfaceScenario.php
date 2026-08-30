<?php

declare(strict_types=1);

namespace Tools\Doctor\Project\BoardPrep\Simulation\Scenarios;

use Tools\Doctor\Project\BoardPrep\Simulation\ApplicationSimulator;
use Tools\Doctor\Project\BoardPrep\Simulation\SimulationScenario;

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
         * 1. Learner dashboard
         *
         * Verifies that the dashboard can build its
         * learning overview and recommendation and render normally.
         */
        $simulation
            ->get('/dashboard')
            ->execute()
            ->assertSuccessful()
            ->assertContains('Learner Dashboard')
            ->assertContains('Learning Overview')
            ->assertContains('Recommended Next Step');

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
            ->assertContains('Study Insight')
            ->assertContains('Recommended Next Steps');

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
            ->assertContains('Progress')
            ->assertContains('Learning Overview')
            ->assertContains('Subject Performance')
            ->assertContains('Recent Quiz History');
    }
}
