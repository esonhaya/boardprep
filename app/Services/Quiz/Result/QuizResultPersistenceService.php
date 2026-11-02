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

        $saveOutcome = App::container()
            ->get(AttemptService::class)
            ->saveWithStatus($attempt);
        $savedAttempt = $saveOutcome['attempt'];

        $draftId = is_scalar($session['durable_draft_id'] ?? null)
            ? trim((string) $session['durable_draft_id'])
            : '';
        $attemptId = is_scalar($savedAttempt['id'] ?? null) ? trim((string) $savedAttempt['id']) : '';
        if ($draftId !== '' && $attemptId !== '') {
            (new \App\Services\Quiz\ExamDraftService())->deleteAfterFinalization($draftId, $attemptId);
        }

        if ($attemptId !== '') {
            SessionService::set('quiz_attempt_id', $attemptId);
        }

        SessionService::set("attempt_persisted", true);
        SessionService::set("quiz_completed", true);

        if (($saveOutcome['created'] ?? false) === true) {
            QuizAnswerStatisticsRecorder::record($questions, $answers);
        }
    }
}
