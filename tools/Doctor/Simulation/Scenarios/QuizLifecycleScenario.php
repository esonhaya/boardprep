<?php

declare(strict_types=1);

namespace Tools\Doctor\Simulation\Scenarios;

use Tools\Doctor\Simulation\ApplicationSimulator;
use Tools\Doctor\Simulation\SimulationScenario;

final class QuizLifecycleScenario extends SimulationScenario
{
    public function name(): string
    {
        return 'Quiz lifecycle';
    }

    public function run(
        ApplicationSimulator $simulation
    ): void {

        $simulation
            ->get('/quiz')
            ->execute()
            ->assertSuccessful()
            ->assertContains('Quiz');

        $simulation
            ->get(
                '/quiz?action=start'
                . '&exam=LET'
                . '&subject=English'
                . '&domain=Grammar'
                . '&difficulty=mixed'
                . '&count=1'
                . '&mode=practice'
            )
            ->execute()
            ->assertSuccessful();

        $response =
            $simulation->responseData();

        if ($response === null) {
            throw new \RuntimeException(
                'Quiz start produced no response.'
            );
        }

        if ($response->body === '') {
            throw new \RuntimeException(
                'Quiz start produced an empty response.'
            );
        }

        $simulation
            ->post(
                '/quiz?action=submit',
                [
                    'answer' => 'simulation-answer',
                ]
            )
            ->execute()
            ->assertSuccessful();

        $simulation
            ->get('/quiz?action=finish')
            ->execute()
            ->assertSuccessful()
            ->assertContains('Quiz Result');
    }
}
