<?php

declare(strict_types=1);

namespace App\Services\Learning;

final class LearningProgressService
{
    private const RECENT_ATTEMPT_LIMIT = 5;
    private const MIN_RECENT_ANSWER_EVENTS = 5;

    public static function build(array $attempts): array
    {
        $attemptTotal = LearningAttemptNormalizer::total($attempts);
        $attempts = LearningHistoryService::ordered($attempts);
        return self::buildFromOrdered($attempts, $attemptTotal);
    }

    /** Build from normalized, newest-first history when a caller already has it. */
    public static function buildFromOrdered(array $attempts, ?int $attemptTotal = null): array
    {
        $attemptTotal ??= count($attempts);
        $total = count($attempts);
        $completed = 0;
        $sum = 0;
        $best = 0;
        $practice = 0;
        $exam = 0;
        $answered = 0;
        $unanswered = 0;
        $correct = 0;
        $incorrect = 0;

        foreach ($attempts as $attempt) {
            if (($attempt["completed"] ?? true) === true) {
                $completed++;
            }

            $percentage = (int) ($attempt["percentage"] ?? 0);
            $sum += $percentage;
            $best = max($best, $percentage);
            $answered += (int) $attempt['answered'];
            $unanswered += (int) $attempt['unanswered'];
            $correct += (int) $attempt['correct'];
            $incorrect += (int) $attempt['incorrect'];

            if (($attempt["mode"] ?? "") === "practice") {
                $practice++;
            }

            if (($attempt["mode"] ?? "") === "exam") {
                $exam++;
            }
        }

        $recentAttemptCount = 0;
        $recentAnswered = 0;
        $recentCorrect = 0;
        foreach ($attempts as $attempt) {
            if (($attempt['completed'] ?? true) !== true || $recentAttemptCount >= self::RECENT_ATTEMPT_LIMIT) {
                continue;
            }
            $recentAttemptCount++;
            $recentAnswered += (int) ($attempt['answered'] ?? 0);
            $recentCorrect += min((int) ($attempt['correct'] ?? 0), (int) ($attempt['answered'] ?? 0));
        }
        $recentAccuracyStatus = $recentAnswered === 0
            ? 'NO_ANSWERED_QUESTIONS'
            : ($recentAnswered < self::MIN_RECENT_ANSWER_EVENTS ? 'INSUFFICIENT_SAMPLE' : 'AVAILABLE');

        return [
            "totalAttempts" => $attemptTotal,
            "completedAttempts" => $completed,
            "averageScore" => $total > 0 ? (int) round($sum / $total) : 0,
            "bestScore" => $best,
            "practiceAttempts" => $practice,
            "examAttempts" => $exam,
            "answeredCount" => $answered,
            "unansweredCount" => $unanswered,
            "correctCount" => $correct,
            "incorrectCount" => $incorrect,
            "accuracy" => $answered > 0 ? (int) round(($correct / $answered) * 100) : 0,
            "recentAccuracy" => $recentAnswered >= self::MIN_RECENT_ANSWER_EVENTS
                ? (int) round(($recentCorrect / $recentAnswered) * 100)
                : null,
            "recentAnsweredCount" => $recentAnswered,
            "recentCorrectCount" => $recentCorrect,
            "recentAttemptCount" => $recentAttemptCount,
            "recentAttemptLimit" => self::RECENT_ATTEMPT_LIMIT,
            "recentAccuracyStatus" => $recentAccuracyStatus,
            "trend" => PerformanceTrendService::summarizeOrdered($attempts),
            "latest" => $attempts[0] ?? null,
        ];
    }
}
