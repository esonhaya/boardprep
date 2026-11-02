<?php

declare(strict_types=1);

final class QuizResultService
{
    public static function build(): array
    {
        try {
            return (new \App\Services\Quiz\ExamDraftService())->withCurrentDraftFinalization(
                static fn(): array => self::buildUnlocked()
            );
        } catch (\App\Exceptions\ExamDraftConflictException $exception) {
            $restored = false;
            try {
                $restored = (new \App\Services\Quiz\ExamDraftService())->restoreAuthoritativeSessionState();
            } catch (\Throwable) {
                // Do not claim the result is current when the draft cannot be reloaded.
            }
            \SessionService::flash('error', $restored
                ? $exception->getMessage()
                : 'The submitted exam could not be verified against its saved state. Reload after account storage is available.');
            \App\Core\Response::redirect($restored ? '/quiz' : '/quiz?action=start', 303);
        } catch (\App\Exceptions\ExamDraftUnavailableException $exception) {
            \SessionService::flash('error', 'The saved exam could not be verified and was not submitted. Open the quiz page to recover it.');
            \App\Core\Response::redirect('/quiz?action=start', 303);
        }
    }

    private static function buildUnlocked(): array
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
            $reviewFlagSnapshot = \App\Services\Quiz\ExamReviewFlagService::snapshotForAttempt(
                $session,
                $questions
            );
            $attempt = QuizResultAttemptFactory::create(
                $session,
                $questions,
                $summary,
                $reviewFlagSnapshot
            );

            QuizResultPersistenceService::persist(
                $attempt,
                $session,
                $questions,
                $answers
            );

            if ($reviewFlagSnapshot !== null) {
                $summary['review_flag_snapshot'] = $reviewFlagSnapshot;
            }
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
