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
         * GET /quiz is the production settings route. Visiting it while a
         * quiz is active must not persist an attempt or destroy the active
         * server-side quiz session needed by the following submission.
         */
        $simulation
            ->get('/quiz')
            ->execute()
            ->assertSuccessful()
            ->assertContains('Quiz');

        $attemptsAfterSettingsVisit = array_values(array_filter(
            $attempts->all(),
            static fn (array $attempt): bool => !in_array(
                (string) ($attempt['id'] ?? ''),
                $beforeIds,
                true
            ) && trim((string) ($attempt['id'] ?? '')) !== ''
        ));
        if ($attemptsAfterSettingsVisit !== []) {
            throw new \RuntimeException(
                'Visiting quiz settings during an active session persisted an attempt prematurely.'
            );
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

        $afterPractice = array_values(array_filter(
            $attempts->all(),
            static fn (array $attempt): bool => !in_array(
                (string) ($attempt['id'] ?? ''),
                $beforeIds,
                true
            ) && trim((string) ($attempt['id'] ?? '')) !== ''
        ));
        if (count($afterPractice) !== 1
            || ($afterPractice[0]['session_type'] ?? null) !== 'quiz') {
            throw new \RuntimeException(sprintf(
                'Practice completion was not persisted exactly once (found %d new attempt(s)).',
                count($afterPractice)
            ));
        }
        $practiceAttempt = $afterPractice[0];
        $practiceId = (string) ($practiceAttempt['id'] ?? '');

        /*
         * Revisit the completed practice result. QuizResultService should
         * serve the cached result and the persistence guard must prevent a
         * duplicate attempt.
         */
        $simulation
            ->get('/quiz?action=result')
            ->execute()
            ->assertSuccessful()
            ->assertContains('Quiz Result');

        $afterPracticeResultReload = array_values(array_filter(
            $attempts->all(),
            static fn (array $attempt): bool => !in_array(
                (string) ($attempt['id'] ?? ''),
                $beforeIds,
                true
            ) && trim((string) ($attempt['id'] ?? '')) !== ''
        ));
        if ($afterPracticeResultReload !== $afterPractice) {
            throw new \RuntimeException(
                'Reloading the completed practice result duplicated or changed learner history.'
            );
        }

        /*
         * Navigating back to the ordinary quiz settings page must not destroy
         * the completed result. Returning to the result remains safe and
         * exact-once.
         */
        $simulation
            ->get('/quiz')
            ->execute()
            ->assertSuccessful()
            ->assertContains('Quiz');

        $simulation
            ->get('/quiz?action=result')
            ->execute()
            ->assertSuccessful()
            ->assertContains('Quiz Result');

        if ($afterPractice !== array_values(array_filter(
            $attempts->all(),
            static fn (array $attempt): bool => !in_array(
                (string) ($attempt['id'] ?? ''),
                $beforeIds,
                true
            ) && trim((string) ($attempt['id'] ?? '')) !== ''
        ))) {
            throw new \RuntimeException(
                'Settings/result navigation after practice completion changed learner history.'
            );
        }

        /*
         * 5. Replace the completed practice session with a real exam session.
         * Answering one question exercises exam-only navigation before a
         * partial exam is completed through the normal finish path.
         */
        $simulation
            ->post('/quiz', [
                'action' => 'start',
                'exam' => 'LET',
                'subject' => 'English',
                'difficulty' => 'mixed',
                'count' => 3,
                'mode' => 'exam',
            ])
            ->execute()
            ->assertSuccessful()
            ->assertContains('Question 1 / 3');

        $examBody = $simulation->context()->get('http')['output'] ?? '';
        if (!is_string($examBody)
            || !preg_match('/name="question_id"\s+value="([^"]+)"/', $examBody, $examMatch)) {
            throw new \RuntimeException('Generated exam did not expose its active question identifier.');
        }

        $simulation
            ->post('/quiz?action=submit', [
                'action' => 'submit',
                'question_id' => $examMatch[1],
                'answer' => 'simulation-answer',
            ])
            ->execute()
            ->assertSuccessful()
            ->assertContains('Question 2 / 3');

        $simulation
            ->post('/quiz', ['action' => 'finish'])
            ->execute()
            ->assertStatus(303);

        $simulation
            ->get('/quiz?action=result')
            ->execute()
            ->assertSuccessful()
            ->assertContains('Quiz Result');

        $afterExam = array_values(array_filter(
            $attempts->all(),
            static fn (array $attempt): bool => !in_array(
                (string) ($attempt['id'] ?? ''),
                $beforeIds,
                true
            ) && trim((string) ($attempt['id'] ?? '')) !== ''
        ));
        $examAttempts = array_values(array_filter(
            $afterExam,
            static fn (array $attempt): bool => ($attempt['session_type'] ?? null) === 'exam_simulation'
        ));
        if (count($afterExam) !== 2 || count($examAttempts) !== 1) {
            throw new \RuntimeException('Practice and exam completions were not persisted exactly once each.');
        }

        $preservedPractice = null;
        foreach ($afterExam as $attempt) {
            if ((string) ($attempt['id'] ?? '') === $practiceId) {
                $preservedPractice = $attempt;
                break;
            }
        }
        if ($preservedPractice !== $practiceAttempt) {
            throw new \RuntimeException('Exam completion changed the earlier practice attempt.');
        }

        /*
         * A stale answer submission after completion is rejected by the
         * persistence guard and redirected back through finish. It must not
         * revive practice state or duplicate either completion.
         */
        $simulation
            ->post('/quiz?action=submit', [
                'action' => 'submit',
                'question_id' => $examMatch[1],
                'answer' => 'simulation-answer',
            ])
            ->execute()
            ->assertStatus(302);

        $staleSubmit = $simulation->context()->get('http');
        if (!is_array($staleSubmit)
            || ($staleSubmit['location'] ?? null) !== '/quiz?action=finish') {
            throw new \RuntimeException('Post-completion exam submission was not rejected safely.');
        }
        if ($afterExam !== array_values(array_filter(
            $attempts->all(),
            static fn (array $attempt): bool => !in_array(
                (string) ($attempt['id'] ?? ''),
                $beforeIds,
                true
            ) && trim((string) ($attempt['id'] ?? '')) !== ''
        ))) {
            throw new \RuntimeException('Post-completion exam submission changed learner history.');
        }

        /*
         * A browser-back-style replay of an old next action after completion
         * must remain behind QuizNavigationService's completion guard.
         */
        $simulation
            ->post('/quiz?action=next', ['action' => 'next'])
            ->execute()
            ->assertStatus(302);

        $completedNavigation = $simulation->context()->get('http');
        if (!is_array($completedNavigation)
            || ($completedNavigation['location'] ?? null) !== '/quiz?action=finish') {
            throw new \RuntimeException(
                'Post-completion navigation did not remain behind the completion guard.'
            );
        }

        if ($afterExam !== array_values(array_filter(
            $attempts->all(),
            static fn (array $attempt): bool => !in_array(
                (string) ($attempt['id'] ?? ''),
                $beforeIds,
                true
            ) && trim((string) ($attempt['id'] ?? '')) !== ''
        ))) {
            throw new \RuntimeException(
                'Post-completion navigation changed learner history.'
            );
        }

        /*
         * The completed exam result must remain revisit-able after stale
         * submit/navigation attempts without another persistence pass.
         */
        $simulation
            ->get('/quiz?action=result')
            ->execute()
            ->assertSuccessful()
            ->assertContains('Quiz Result');

        if ($afterExam !== array_values(array_filter(
            $attempts->all(),
            static fn (array $attempt): bool => !in_array(
                (string) ($attempt['id'] ?? ''),
                $beforeIds,
                true
            ) && trim((string) ($attempt['id'] ?? '')) !== ''
        ))) {
            throw new \RuntimeException(
                'Reloading the completed exam result changed learner history.'
            );
        }

        /*
         * 6. Exercise a production shortage with a controlled question pool.
         *
         * Production intentionally degrades to the eligible pool size. Draft
         * and archived questions are real authoring states, while the Math
         * question proves that subject taxonomy cannot leak into English.
         */
        $questions->replaceAll([
            self::question('simulation-active', 'Active eligible question?', 'active', 'English'),
            self::question('simulation-approved', 'Approved eligible question?', 'approved', 'English'),
            self::question('simulation-draft', 'Draft ineligible question?', 'draft', 'English'),
            self::question('simulation-archived', 'Archived ineligible question?', 'archived', 'English'),
            self::question('simulation-other-subject', 'Other taxonomy question?', 'approved', 'Mathematics'),
        ]);

        $attemptIdsBeforeFailure = array_map(
            'strval',
            array_column($attempts->all(), 'id')
        );

        $simulation
            ->post('/quiz', [
                'action' => 'start',
                'exam' => 'LET',
                'subject' => 'English',
                'difficulty' => 'mixed',
                'count' => 5,
                'mode' => 'practice',
            ])
            ->execute()
            ->assertSuccessful()
            ->assertContains('Question 1 / 2')
            ->assertNotContains('Draft ineligible question?')
            ->assertNotContains('Archived ineligible question?')
            ->assertNotContains('Other taxonomy question?');

        $shortageBody = $simulation->context()->get('http')['output'] ?? '';
        if (!is_string($shortageBody)
            || !preg_match('/name="question_id"\s+value="([^"]+)"/', $shortageBody, $shortageMatch)) {
            throw new \RuntimeException('Shortage quiz did not expose its first eligible question.');
        }

        $selectedIds = [$shortageMatch[1]];
        $simulation
            ->post('/quiz?action=submit', [
                'action' => 'submit',
                'question_id' => $shortageMatch[1],
                'answer' => 'A',
            ])
            ->execute()
            ->assertSuccessful();

        $simulation
            ->post('/quiz?action=next', ['action' => 'next'])
            ->execute()
            ->assertSuccessful()
            ->assertContains('Question 2 / 2')
            ->assertNotContains('Draft ineligible question?')
            ->assertNotContains('Archived ineligible question?')
            ->assertNotContains('Other taxonomy question?');

        $shortageBody = $simulation->context()->get('http')['output'] ?? '';
        if (!is_string($shortageBody)
            || !preg_match('/name="question_id"\s+value="([^"]+)"/', $shortageBody, $shortageMatch)) {
            throw new \RuntimeException('Shortage quiz did not expose its second eligible question.');
        }
        $selectedIds[] = $shortageMatch[1];
        sort($selectedIds);
        if ($selectedIds !== ['simulation-active', 'simulation-approved']) {
            throw new \RuntimeException('Production shortage selection included ineligible or cross-taxonomy content.');
        }
        if ($attemptIdsBeforeFailure !== array_map('strval', array_column($attempts->all(), 'id'))) {
            throw new \RuntimeException('Starting a shortage quiz persisted an attempt before completion.');
        }

        /*
         * 7. An invalid taxonomy filter creates an empty eligible pool. The
         * failed replacement must clear the still-active shortage quiz.
         */
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
         * 8. Finishing after recovery must remain safe and cannot reactivate
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

    private static function question(
        string $id,
        string $text,
        string $status,
        string $subject
    ): array {
        return [
            'id' => $id,
            'question' => $text,
            'choices' => ['A', 'B'],
            'answer' => 'A',
            'explanation' => 'Simulation eligibility fixture.',
            'difficulty' => 'easy',
            'status' => $status,
            'subject' => $subject,
            'domain' => 'Grammar',
            'topic' => 'Parts of Speech',
            'taxonomy' => [
                'board_id' => 'LET',
                'subject_id' => $subject,
                'domain_id' => 'Grammar',
                'topic_id' => 'Parts of Speech',
            ],
        ];
    }
}
