<?php

declare(strict_types=1);

final class QuizResultService
{
    public static function build(): array
    {
        $cached = SessionService::get("quiz_result", null);
        if (self::validCachedResult($cached)) {
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

    private static function validCachedResult(mixed $cached): bool
    {
        if (!is_array($cached) || !is_array($cached['results'] ?? null)) {
            return false;
        }

        foreach (['score', 'total', 'percentage'] as $field) {
            if (!isset($cached[$field]) || !is_numeric($cached[$field])
                || !is_finite((float) $cached[$field])) {
                return false;
            }
        }

        foreach ($cached['results'] as $result) {
            if (!is_array($result)
                || !\App\Services\Quiz\Session\QuizSessionQuestion::isRenderable(
                    $result['question'] ?? null
                )) {
                return false;
            }
        }

        return true;
    }
}
