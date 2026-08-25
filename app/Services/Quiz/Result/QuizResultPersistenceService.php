<?php

declare(strict_types=1);

use App\Core\App;
use App\Services\AttemptService;

final class QuizResultPersistenceService
{
    /**
     * @param array<string,mixed> $attempt
     * @param array<string,mixed> $session
     * @param array<int,array<string,mixed>> $questions
     */
    public static function persist(
        array $attempt,
        array $session,
        array $questions
    ): void {
        $attempt = QuizLearningContextService::enrichAttempt(
            $attempt,
            $session,
            $questions
        );

        App::container()
            ->get(AttemptService::class)
            ->save($attempt);

        SessionService::set("attempt_persisted", true);
    }
}
