<?php

declare(strict_types=1);

final class QuizResultService
{
    public static function build(): array
    {
        $cached = SessionService::get("quiz_result", null);
        if (is_array($cached)
            && array_key_exists("score", $cached)
            && array_key_exists("total", $cached)
            && array_key_exists("percentage", $cached)
        ) {
            return QuizResultResponseFactory::create($cached);
        }

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
                $questions,
                $answers
            );
        }

        SessionService::set("quiz_result", $summary);

        return QuizResultResponseFactory::create($summary);
    }
}
