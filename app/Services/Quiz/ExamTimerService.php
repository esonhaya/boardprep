<?php

declare(strict_types=1);

namespace App\Services\Quiz;

use App\Core\Response;

final class ExamTimerService
{
    public static function isTimed(array $quizSession): bool
    {
        return ($quizSession['mode'] ?? '') === 'exam'
            && ($quizSession['timer_mode'] ?? '') === 'timed'
            && (int) ($quizSession['time_limit_seconds'] ?? 0) > 0;
    }

    public static function remainingSeconds(array $quizSession, ?int $now = null): ?int
    {
        if (!self::isTimed($quizSession)) {
            return null;
        }

        $deadline = filter_var($quizSession['deadline_epoch'] ?? null, FILTER_VALIDATE_INT);
        if ($deadline === false || $deadline < 1) {
            return 0;
        }

        return max(0, $deadline - ($now ?? time()));
    }

    public static function formatRemaining(int $seconds): string
    {
        $seconds = max(0, $seconds);
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $remainingSeconds = $seconds % 60;

        return $hours > 0
            ? sprintf('%d:%02d:%02d', $hours, $minutes, $remainingSeconds)
            : sprintf('%02d:%02d', $minutes, $remainingSeconds);
    }

    /**
     * Enforce the server deadline before handling any learner action.
     * A missing or invalid deadline on a timed session expires safely; legacy
     * untimed exam sessions remain resumable.
     */
    public static function enforceIfExpired(): void
    {
        $quizSession = \SessionService::get('quiz_session', []);
        if (!is_array($quizSession)
            || !self::isTimed($quizSession)
            || \SessionService::has('attempt_persisted')) {
            return;
        }

        if ((self::remainingSeconds($quizSession) ?? 0) > 0) {
            return;
        }

        $quizSession['time_expired'] = true;
        $quizSession['time_expired_at'] = date('c');
        \SessionService::set('quiz_session', $quizSession);
        \QuizResultService::build();
        Response::redirect('/quiz?action=result', 303);
    }
}
