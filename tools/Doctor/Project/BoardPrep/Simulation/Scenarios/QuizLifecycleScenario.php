<?php

declare(strict_types=1);

namespace Tools\Doctor\Project\BoardPrep\Simulation\Scenarios;

use App\Core\App;
use App\Repositories\AttemptRepository;
use App\Repositories\QuestionRepository;
use App\Services\Learning\WeaknessStorageService;
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
        $attempts = App::container()->get(AttemptRepository::class);
        $questions = App::container()->get(QuestionRepository::class);
        $beforeIds = array_map('strval', array_column($attempts->all(), 'id'));
        $questionsBefore = $questions->all();
        $weaknessesBefore = WeaknessStorageService::all();

        try {

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
            ->post('/quiz', [
                'action' => 'start',
                'exam' => 'LET',
                'subject' => 'English',
                'domain' => 'Language',
                'difficulty' => 'mixed',
                'count' => 1,
                'mode' => 'practice',
            ])
            ->execute()
            ->assertSuccessful()
            ->assertContains('Quiz');

        $body = $simulation->context()->get('http')['output'] ?? '';
        if (!is_string($body)
            || !preg_match('/name="question_id"\s+value="([^"]+)"/', $body, $match)) {
            throw new \RuntimeException('Generated quiz did not expose its active question identifier.');
        }

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
                    'action' => 'submit',
                    'question_id' => $match[1],
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
            ->post('/quiz', ['action' => 'finish'])
            ->execute()
            ->assertStatus(303);

        $simulation
            ->get('/quiz?action=result')
            ->execute()
            ->assertSuccessful()
            ->assertContains('Quiz Result')
            ->assertContains('Answer Review');

        /*
         * 5. Fail a replacement start through the production HTTP path.
         *
         * An impossible subject must abandon the completed session, expose
         * the learner recovery message, and leave persistence untouched.
         */
        $attemptIdsBeforeFailure = array_map(
            'strval',
            array_column($attempts->all(), 'id')
        );

        $simulation
            ->post('/quiz', [
                'action' => 'start',
                'exam' => 'LET',
                'subject' => 'Simulation Missing Subject',
                'domain' => 'Simulation Missing Domain',
                'difficulty' => 'hard',
                'count' => 20,
                'mode' => 'practice',
            ])
            ->execute()
            ->assertStatus(302);

        $failedStart = $simulation->context()->get('http');
        if (!is_array($failedStart) || ($failedStart['location'] ?? null) !== '/quiz') {
            throw new \RuntimeException('Failed quiz generation did not use the safe recovery redirect.');
        }

        $simulation
            ->get('/quiz')
            ->execute()
            ->assertSuccessful()
            ->assertContains('No questions matched')
            ->assertNotContains('name="question_id"');

        if ($attemptIdsBeforeFailure !== array_map('strval', array_column($attempts->all(), 'id'))) {
            throw new \RuntimeException('Failed quiz generation accidentally persisted learner history.');
        }

        /*
         * 6. Finishing after recovery must remain safe and cannot reactivate
         * stale questions or create another attempt.
         */
        $simulation
            ->post('/quiz', ['action' => 'finish'])
            ->execute()
            ->assertStatus(302);

        $finishAfterFailure = $simulation->context()->get('http');
        if (!is_array($finishAfterFailure) || ($finishAfterFailure['location'] ?? null) !== '/quiz') {
            throw new \RuntimeException('Finish after failed generation did not return to quiz recovery.');
        }

        $simulation
            ->get('/quiz')
            ->execute()
            ->assertSuccessful()
            ->assertContains('stale or invalid')
            ->assertNotContains('name="question_id"');

        if ($attemptIdsBeforeFailure !== array_map('strval', array_column($attempts->all(), 'id'))) {
            throw new \RuntimeException('Finish after failed generation duplicated learner history.');
        }
        } finally {
            foreach ($attempts->all() as $attempt) {
                $id = is_scalar($attempt['id'] ?? null) ? (string) $attempt['id'] : '';
                if ($id !== '' && !in_array($id, $beforeIds, true)) {
                    $attempts->delete($id);
                }
            }
            $questions->replaceAll($questionsBefore);
            WeaknessStorageService::save($weaknessesBefore);
        }
    }
}
