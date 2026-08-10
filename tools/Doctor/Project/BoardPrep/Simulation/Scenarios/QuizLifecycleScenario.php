<?php

declare(strict_types=1);

namespace Tools\Doctor\Project\BoardPrep\Simulation\Scenarios;

use Tools\Doctor\Project\BoardPrep\Simulation\ApplicationSimulator;
use Tools\Doctor\Project\BoardPrep\Simulation\SimulationScenario;

final class QuizLifecycleScenario extends SimulationScenario
{
    public function name(): string
    {
        return 'Quiz lifecycle';
    }

    public function run(
        ApplicationSimulator $simulation
    ): void {

        /*
         * 1. Quiz settings
         */
        $simulation
            ->get('/quiz')
            ->execute()
            ->assertSuccessful()
            ->assertContains('Quiz');

        /*
         * 2. Start a real generated quiz.
         *
         * The current question bank uses English as the
         * subject and Language as the domain.
         */
        $simulation
            ->get(
                '/quiz?action=start'
                . '&exam=LET'
                . '&subject=English'
                . '&domain=Language'
                . '&difficulty=mixed'
                . '&count=1'
                . '&mode=practice'
            )
            ->execute()
            ->assertSuccessful()
            ->assertContains('Quiz');

        /*
         * 3. Submit an answer through the real POST path.
         *
         * We intentionally use a synthetic answer. The lifecycle
         * test verifies persistence, scoring and rendering rather
         * than whether the simulated answer is correct.
         */
        $simulation
            ->post(
                '/quiz?action=submit',
                [
                    'answer' =>
                        'simulation-answer',
                ]
            )
            ->execute()
            ->assertSuccessful();

        /*
         * 4. Build the result from the same persisted session.
         */
        $simulation
            ->get('/quiz?action=finish')
            ->execute()
            ->assertSuccessful()
            ->assertContains('Quiz Result')
            ->assertContains('Answer Review');
    }
}
