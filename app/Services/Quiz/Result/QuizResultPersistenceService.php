<?php

declare(strict_types=1);

use App\Core\App;
use App\Services\AttemptService;
use App\Services\Quiz\QuizLearningContextService;
use App\Services\Quiz\Result\QuizAnswerStatisticsRecorder;

final class QuizResultPersistenceService
{
    /**
     * @param array<string,mixed> $attempt
     * @param array<string,mixed> $session
     * @param array<int,array<string,mixed>> $questions
     * @param array<string,mixed> $answers
     */
    public static function persist(
        array $attempt,
        array $session,
        array $questions,
        array $answers = []
    ): void {
        if (SessionService::has("attempt_persisted")) {
            return;
        }

        $attempt = QuizLearningContextService::enrichAttempt(
            $attempt,
            $session,
            $questions
        );

        App::container()
            ->get(AttemptService::class)
            ->save($attempt);

        SessionService::set("attempt_persisted", true);
        SessionService::set("quiz_completed", true);

        QuizAnswerStatisticsRecorder::record($questions, $answers);
    }
}
