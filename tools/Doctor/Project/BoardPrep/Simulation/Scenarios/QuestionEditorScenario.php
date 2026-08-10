<?php

declare(strict_types=1);

namespace Tools\Doctor\Project\BoardPrep\Simulation\Scenarios;

use Tools\Doctor\Project\BoardPrep\Simulation\ApplicationSimulator;
use Tools\Doctor\Project\BoardPrep\Simulation\SimulationScenario;

final class QuestionEditorScenario extends SimulationScenario
{
    public function name(): string
    {
        return 'Question editor';
    }

    public function run(
        ApplicationSimulator $simulation
    ): void {

        /*
         * 1. Question editor index.
         */
        $simulation
            ->get('/question-editor')
            ->execute()
            ->assertSuccessful()
            ->assertContains('Question Editor');

        /*
         * 2. Question creation workspace.
         */
        $simulation
            ->get('/question-editor/create')
            ->execute()
            ->assertSuccessful()
            ->assertContains('Create Question');

        /*
         * 3. Question editor with harmless filters.
         *
         * This verifies that the editor can process its
         * normal query-string filtering path.
         */
        $simulation
            ->get(
                '/question-editor'
                . '?subject=English'
                . '&domain=Language'
                . '&difficulty=mixed'
            )
            ->execute()
            ->assertSuccessful()
            ->assertContains('Question Editor');
    }
}
