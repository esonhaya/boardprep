<?php

declare(strict_types=1);

final class QuizResultService
{
    public static function build(): array
    {
        $input = QuizResultSessionReader::read();

        $questions = $input["questions"];
        $answers = $input["answers"];
        $session = $input["session"];

        $summary = QuizScoringService::calculate(
            $questions,
            $answers
        );

        if (
            QuizResultPersistenceGuard::shouldPersist(
                $session,
                SessionService::has("attempt_persisted")
            )
        ) {
            $attempt = QuizResultAttemptFactory::create(
                $session,
                $questions,
                $summary
            );

            QuizResultPersistenceService::persist(
                $attempt,
                $session,
                $questions
            );
        }

        return QuizResultResponseFactory::create($summary);
    }
}
