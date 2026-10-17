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
        $csrfToken = $this->csrfToken($simulation);

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
                'csrf_token' => $csrfToken,
            ])
            ->execute()
            ->assertSuccessful()
            ->assertContains('Question 1 / 1');

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
            ->assertContains('Question 1 / 1');

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
                    'csrf_token' => $csrfToken,
                ]
            )
            ->execute()
            ->assertSuccessful();

        /*
         * 4. Build the result from the same persisted session.
         */
        $simulation
            ->post('/quiz', ['action' => 'finish', 'csrf_token' => $csrfToken])
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
                'csrf_token' => $csrfToken,
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
                'csrf_token' => $csrfToken,
            ])
            ->execute()
            ->assertSuccessful()
            ->assertContains('Question 2 / 3');

        $simulation
            ->get('/quiz?action=review')
            ->execute()
            ->assertSuccessful()
            ->assertContains('Review your answers')
            ->assertContains('Submit exam simulation');

        $simulation
            ->post('/quiz', ['action' => 'finish', 'csrf_token' => $csrfToken])
            ->execute()
            ->assertStatus(303);

        $simulation
            ->get('/quiz?action=result')
            ->execute()
            ->assertSuccessful()
            ->assertContains('Exam Simulation Result');

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
            $types = array_map(
                static fn (array $attempt): string => (string) ($attempt['session_type'] ?? 'missing'),
                $afterExam
            );
            throw new \RuntimeException(sprintf(
                'Practice and exam completions were not persisted exactly once each (new=%d, exam=%d, types=%s).',
                count($afterExam),
                count($examAttempts),
                implode(',', $types)
            ));
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
                'csrf_token' => $csrfToken,
            ])
            ->execute()
            ->assertStatus(302);

        $staleSubmit = $simulation->context()->get('http');
        if (!is_array($staleSubmit)
            || ($staleSubmit['location'] ?? null) !== '/quiz?action=result') {
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
            ->post('/quiz?action=next', ['action' => 'next', 'csrf_token' => $csrfToken])
            ->execute()
            ->assertStatus(302);

        $completedNavigation = $simulation->context()->get('http');
        if (!is_array($completedNavigation)
            || ($completedNavigation['location'] ?? null) !== '/quiz?action=result') {
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
            ->assertContains('Exam Simulation Result');

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
         * 6. Invalidate the active question after generation. The production
         * submit guard must re-check repository state, clear the session, and
         * avoid every learner-history side effect.
         */
        $invalidationAttempts = $attempts->all();
        $invalidationWeaknesses = WeaknessStorageService::all();
        $questions->replaceAll([
            self::question('simulation-mid-session', 'Mid-session question?', 'approved', 'English'),
        ]);
        $simulation
            ->post('/quiz', [
                'action' => 'start', 'exam' => 'LET', 'subject' => 'English',
                'difficulty' => 'mixed', 'count' => 1, 'mode' => 'practice',
                'csrf_token' => $csrfToken,
            ])
            ->execute()
            ->assertSuccessful()
            ->assertContains('Mid-session question?');

        $questions->replaceAll([
            self::question('simulation-mid-session', 'Mid-session question?', 'archived', 'English'),
        ]);
        $simulation
            ->post('/quiz?action=submit', [
                'action' => 'submit', 'question_id' => 'simulation-mid-session', 'answer' => 'A',
                'csrf_token' => $csrfToken,
            ])
            ->execute()
            ->assertStatus(302);
        $invalidSubmit = $simulation->context()->get('http');
        if (!is_array($invalidSubmit) || ($invalidSubmit['location'] ?? null) !== '/quiz') {
            throw new \RuntimeException('Invalidated submit did not redirect to quiz recovery.');
        }
        $simulation
            ->get('/quiz')
            ->execute()
            ->assertSuccessful()
            ->assertContains('stale or invalid')
            ->assertNotContains('name="question_id"');
        if ($attempts->all() !== $invalidationAttempts
            || WeaknessStorageService::all() !== $invalidationWeaknesses) {
            throw new \RuntimeException('Invalidated submit persisted learner side effects.');
        }
        $simulation
            ->get('/quiz?action=result')
            ->execute()
            ->assertStatus(302);
        $invalidResult = $simulation->context()->get('http');
        if (!is_array($invalidResult) || ($invalidResult['location'] ?? null) !== '/quiz') {
            throw new \RuntimeException('Invalidated submit fabricated a result.');
        }

        /*
         * 7. Exercise a production shortage with a controlled question pool.
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
                'csrf_token' => $csrfToken,
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
                'csrf_token' => $csrfToken,
            ])
            ->execute()
            ->assertSuccessful();

        $simulation
            ->post('/quiz?action=next', ['action' => 'next', 'csrf_token' => $csrfToken])
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

        $secondShortageQuestionId = $shortageMatch[1];

        /*
         * 8. An invalid taxonomy filter creates an empty eligible pool. The
         * previous session and its answers remain resumable while the new
         * request's error is shown.
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
                'csrf_token' => $csrfToken,
            ])
            ->execute()
            ->assertStatus(302);

        $failedStart = $simulation->context()->get('http');
        $failedLocation = is_array($failedStart) ? (string) ($failedStart['location'] ?? '') : '';
        $failedPath = parse_url($failedLocation, PHP_URL_PATH);
        $failedQuery = [];
        parse_str((string) parse_url($failedLocation, PHP_URL_QUERY), $failedQuery);
        if ($failedPath !== '/quiz'
            || ($failedQuery['exam'] ?? '') !== 'LET'
            || ($failedQuery['subject'] ?? '') !== 'Simulation Missing Subject'
            || ($failedQuery['difficulty'] ?? '') !== 'hard'
            || (string) ($failedQuery['count'] ?? '') !== '20') {
            throw new \RuntimeException('Failed quiz generation did not use the safe recovery redirect.');
        }

        $simulation
            ->get($failedLocation)
            ->execute()
            ->assertSuccessful()
            ->assertContains('No questions matched')
            ->assertContains('active-session-warning')
            ->assertContains('Resume current session');

        $simulation
            ->get('/quiz')
            ->execute()
            ->assertSuccessful()
            ->assertContains('Question 2 / 2');

        $resumedBody = $simulation->context()->get('http')['output'] ?? '';
        if (!is_string($resumedBody)
            || !preg_match('/name="question_id"\s+value="([^"]+)"/', $resumedBody, $resumedQuestion)
            || $resumedQuestion[1] !== $secondShortageQuestionId) {
            throw new \RuntimeException('Failed quiz generation did not preserve the active question for safe resumption.');
        }

        if ($attemptIdsBeforeFailure !== array_map('strval', array_column($attempts->all(), 'id'))) {
            throw new \RuntimeException('Failed quiz generation accidentally persisted learner history.');
        }

        /*
         * 9. Continue the preserved session after the failed replacement.
         * The learner should be able to submit the current answer without
         * creating a completed attempt prematurely.
         */
        $simulation
            ->post('/quiz?action=submit', [
                'action' => 'submit',
                'question_id' => $secondShortageQuestionId,
                'answer' => 'B',
                'csrf_token' => $csrfToken,
            ])
            ->execute()
            ->assertSuccessful()
            ->assertContains('Explanation:');

        if ($attemptIdsBeforeFailure !== array_map('strval', array_column($attempts->all(), 'id'))) {
            throw new \RuntimeException('Resuming after failed generation changed completed learner history.');
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

    private function csrfToken(ApplicationSimulator $simulation): string
    {
        $body = $simulation->context()->get('http')['output'] ?? '';
        if (!is_string($body) || preg_match('/name="csrf_token"\s+value="([^"]+)"/', $body, $match) !== 1) {
            throw new \RuntimeException('Quiz form did not provide its CSRF token.');
        }
        return html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
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
